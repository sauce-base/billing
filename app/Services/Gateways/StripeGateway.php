<?php

namespace Modules\Billing\Services\Gateways;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Billing\Contracts\PaymentGatewayInterface;
use Modules\Billing\Data\CheckoutData;
use Modules\Billing\Data\CheckoutResultData;
use Modules\Billing\Data\PaymentMethodData;
use Modules\Billing\Data\WebhookData;
use Modules\Billing\Enums\WebhookEventType;
use Modules\Billing\Models\Customer;
use Modules\Billing\Models\Subscription;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StripeGateway implements PaymentGatewayInterface
{
    private const array EVENT_MAP = [
        'checkout.session.completed' => WebhookEventType::CheckoutCompleted,
        'customer.subscription.updated' => WebhookEventType::SubscriptionUpdated,
        'customer.subscription.deleted' => WebhookEventType::SubscriptionDeleted,
        'invoice.payment_succeeded' => WebhookEventType::PaymentSucceeded,
        'invoice.payment_failed' => WebhookEventType::PaymentFailed,
        'invoice.paid' => WebhookEventType::InvoicePaid,
    ];

    public function __construct(
        private StripeClient $stripe,
    ) {}

    public function createCustomer(string $name, string $email): string
    {
        $customer = $this->stripe->customers->create([
            'name' => $name,
            'email' => $email,
        ]);

        return $customer->id;
    }

    public function createCheckoutSession(CheckoutData $data): CheckoutResultData
    {
        $isRecurring = $data->price->interval !== null;

        $params = [
            'customer' => $data->customer->provider_customer_id,
            'mode' => $isRecurring ? 'subscription' : 'payment',
            'line_items' => [
                [
                    'price' => $data->price->provider_price_id,
                    'quantity' => 1,
                ],
            ],
            'success_url' => $data->successUrl,
            'cancel_url' => $data->cancelUrl,
        ];

        if ($data->coupon) {
            $params['discounts'] = [['coupon' => $data->coupon]];
        }

        $session = $this->stripe->checkout->sessions->create($params);

        return new CheckoutResultData(
            sessionId: $session->id,
            url: $session->url,
            provider: 'stripe',
        );
    }

    public function cancelSubscription(Subscription $subscription, bool $immediately = false): ?\DateTimeInterface
    {
        if ($immediately) {
            $this->stripe->subscriptions->cancel($subscription->provider_subscription_id);

            return null;
        }

        $stripeSub = $this->stripe->subscriptions->update($subscription->provider_subscription_id, [
            'cancel_at_period_end' => true,
        ]);

        $endsAt = $stripeSub->current_period_end ?? $stripeSub->cancel_at;

        return $endsAt ? Carbon::createFromTimestamp($endsAt) : null;
    }

    public function resumeSubscription(Subscription $subscription): void
    {
        $this->stripe->subscriptions->update($subscription->provider_subscription_id, [
            'cancel_at_period_end' => false,
        ]);
    }

    public function getManagementUrl(Customer $customer): string
    {
        $session = $this->stripe->billingPortal->sessions->create([
            'customer' => $customer->provider_customer_id,
            'return_url' => route('settings.billing'),
        ]);

        return $session->url;
    }

    public function resolvePaymentMethod(string $providerId): ?PaymentMethodData
    {
        $pmId = match (true) {
            str_starts_with($providerId, 'pm_') => $providerId,
            str_starts_with($providerId, 'sub_') => $this->stripe->subscriptions->retrieve($providerId)->default_payment_method,
            str_starts_with($providerId, 'pi_') => $this->stripe->paymentIntents->retrieve($providerId)->payment_method,
            default => null,
        };

        if (! $pmId) {
            return null;
        }

        $pm = $this->stripe->paymentMethods->retrieve($pmId);

        return new PaymentMethodData(
            providerPaymentMethodId: $pm->id,
            type: $pm->type,
            cardBrand: $pm->card?->brand,
            cardLastFour: $pm->card?->last4,
            cardExpMonth: $pm->card?->exp_month,
            cardExpYear: $pm->card?->exp_year,
        );
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        return $this->stripe->checkout->sessions->retrieve($sessionId)->toArray();
    }

    public function verifyAndParseWebhook(Request $request): WebhookData
    {
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $webhookSecret,
            );
        } catch (SignatureVerificationException $e) {
            throw new HttpException(400, 'Invalid webhook signature: '.$e->getMessage());
        }

        $normalizedType = self::EVENT_MAP[$event->type] ?? null;

        return new WebhookData(
            type: $normalizedType,
            provider: 'stripe',
            providerEventId: $event->id,
            payload: $event->data->object->toArray(),
        );
    }
}
