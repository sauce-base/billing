<?php

namespace Modules\Billing\Services\Gateways;

use App\Models\User;
use Carbon\Carbon;
use Modules\Billing\Contracts\PaymentGatewayInterface;
use Modules\Billing\Exceptions\PaymentException;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Models\SubscriptionPlanPrice;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeGateway extends BaseGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected StripeClient $stripe
    ) {}

    /**
     * Get the gateway identifier name.
     */
    public function getName(): string
    {
        return 'stripe';
    }

    /**
     * Get the publishable/public key for this gateway.
     */
    public function getPublishableKey(): string
    {
        return config('services.stripe.publishable_key');
    }

    public function createSubscription(
        User $user,
        SubscriptionPlanPrice $planPrice,
        string $paymentMethodId
    ): Subscription {
        try {
            $this->logOperation('createSubscription', [
                'user_id' => $user->id,
                'plan_price_id' => $planPrice->id,
            ]);

            // Get or create Stripe customer
            $customerId = $this->getOrCreateCustomer($user, $paymentMethodId);

            // Create subscription in Stripe
            $stripeSubscription = $this->stripe->subscriptions->create([
                'customer' => $customerId,
                'items' => [['price' => $planPrice->getProviderPriceId('stripe')]],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            // Save to database
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $planPrice->subscription_plan_id,
                'subscription_plan_price_id' => $planPrice->id,
                'provider' => 'stripe',
                'provider_subscription_id' => $stripeSubscription->id,
                'provider_metadata' => [
                    'customer_id' => $customerId,
                    'latest_invoice_id' => $stripeSubscription->latest_invoice->id ?? null,
                ],
                'status' => $stripeSubscription->status,
                'current_period_start' => Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            ]);

            $this->logOperation('createSubscription.success', [
                'subscription_id' => $subscription->id,
                'provider_subscription_id' => $stripeSubscription->id,
            ]);

            return $subscription;
        } catch (ApiErrorException $e) {
            $this->handleApiError($e, [
                'operation' => 'createSubscription',
                'user_id' => $user->id,
                'plan_price_id' => $planPrice->id,
            ]);
        }
    }

    public function cancelSubscription(Subscription $subscription): Subscription
    {
        try {
            $this->logOperation('cancelSubscription', [
                'subscription_id' => $subscription->id,
                'provider_subscription_id' => $subscription->provider_subscription_id,
            ]);

            // Cancel at period end (grace period)
            $stripeSubscription = $this->stripe->subscriptions->update(
                $subscription->provider_subscription_id,
                ['cancel_at_period_end' => true]
            );

            $subscription->update([
                'status' => $stripeSubscription->status,
                'canceled_at' => now(),
                'ends_at' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            ]);

            return $subscription;
        } catch (ApiErrorException $e) {
            $this->handleApiError($e, [
                'operation' => 'cancelSubscription',
                'subscription_id' => $subscription->id,
            ]);
        }
    }

    public function resumeSubscription(Subscription $subscription): Subscription
    {
        // Only works if still in grace period
        if (! $subscription->ends_at || $subscription->ends_at->isPast()) {
            throw PaymentException::cannotResumeExpiredSubscription();
        }

        try {
            $this->logOperation('resumeSubscription', [
                'subscription_id' => $subscription->id,
                'provider_subscription_id' => $subscription->provider_subscription_id,
            ]);

            $stripeSubscription = $this->stripe->subscriptions->update(
                $subscription->provider_subscription_id,
                ['cancel_at_period_end' => false]
            );

            $subscription->update([
                'status' => $stripeSubscription->status,
                'canceled_at' => null,
                'ends_at' => null,
            ]);

            return $subscription;
        } catch (ApiErrorException $e) {
            $this->handleApiError($e, [
                'operation' => 'resumeSubscription',
                'subscription_id' => $subscription->id,
            ]);
        }
    }

    public function verifyAndParseWebhook(string $payload, string $signature): array
    {
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );

            return [
                'id' => $event->id,
                'type' => $event->type,
                'data' => $event->data->toArray(),
            ];
        } catch (\Exception $e) {
            throw PaymentException::invalidWebhookSignature();
        }
    }

    protected function getOrCreateCustomer(User $user, string $paymentMethodId): string
    {
        $customerId = $user->getProviderCustomerId('stripe');

        if ($customerId) {
            // Attach payment method to existing customer
            $this->stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $customerId,
            ]);

            return $customerId;
        }

        // Create new customer
        $customer = $this->stripe->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'payment_method' => $paymentMethodId,
            'invoice_settings' => ['default_payment_method' => $paymentMethodId],
            'metadata' => ['user_id' => $user->id],
        ]);

        $user->setProviderCustomerId('stripe', $customer->id);

        return $customer->id;
    }
}
