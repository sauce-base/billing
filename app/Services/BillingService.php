<?php

namespace Modules\Billing\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Data\CheckoutData;
use Modules\Billing\Data\CheckoutResultData;
use Modules\Billing\Data\WebhookData;
use Modules\Billing\Enums\CheckoutSessionStatus;
use Modules\Billing\Enums\Currency;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Enums\WebhookEventType;
use Modules\Billing\Events\CheckoutCompleted;
use Modules\Billing\Events\InvoicePaid;
use Modules\Billing\Events\PaymentFailed;
use Modules\Billing\Events\PaymentSucceeded;
use Modules\Billing\Events\SubscriptionCancelled;
use Modules\Billing\Events\SubscriptionCreated;
use Modules\Billing\Events\SubscriptionUpdated;
use Modules\Billing\Models\CheckoutSession;
use Modules\Billing\Models\Customer;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Models\Subscription;

class BillingService
{
    public function __construct(
        private PaymentGatewayManager $manager,
    ) {}

    /**
     * @param  array<string, mixed>  $billingDetails
     */
    public function processCheckout(CheckoutSession $session, User $user, string $successUrl, string $cancelUrl, array $billingDetails = []): CheckoutResultData
    {
        $customer = $this->ensureCustomer($user, billingDetails: $billingDetails);
        $price = $session->price()->with('product')->firstOrFail();

        $data = new CheckoutData(
            customer: $customer,
            price: $price,
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
        );

        $result = $this->manager->driver()->createCheckoutSession($data);

        $session->update([
            'customer_id' => $customer->id,
            'provider_session_id' => $result->sessionId,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        return $result;
    }

    public function handleWebhook(string $provider, Request $request): void
    {
        $gateway = $this->manager->driver($provider);
        $webhook = $gateway->verifyAndParseWebhook($request);

        Log::info('Webhook received', ['provider' => $provider, 'type' => $webhook->type?->value ?? 'unmapped']);

        match ($webhook->type) {
            WebhookEventType::CheckoutCompleted => $this->onCheckoutCompleted($webhook),
            WebhookEventType::SubscriptionUpdated => $this->onSubscriptionUpdated($webhook),
            WebhookEventType::SubscriptionDeleted => $this->onSubscriptionDeleted($webhook),
            WebhookEventType::PaymentSucceeded => $this->onPaymentSucceeded($webhook),
            WebhookEventType::PaymentFailed => $this->onPaymentFailed($webhook),
            WebhookEventType::InvoicePaid => $this->onInvoicePaid($webhook),
            default => null,
        };
    }

    public function cancel(Subscription $subscription, bool $immediately = false): ?\DateTimeInterface
    {
        return $this->manager->driver()->cancelSubscription($subscription, $immediately);
    }

    public function resume(Subscription $subscription): void
    {
        $this->manager->driver()->resumeSubscription($subscription);
    }

    public function getManagementUrl(User $user): string
    {
        $customer = Customer::where('user_id', $user->id)->firstOrFail();

        return $this->manager->driver()->getManagementUrl($customer);
    }

    /**
     * @param  array<string, mixed>  $billingDetails
     */
    private function ensureCustomer(User $user, ?string $provider = null, array $billingDetails = []): Customer
    {
        $name = $billingDetails['name'] ?? $user->name;
        $email = $billingDetails['email'] ?? $user->email;
        $phone = $billingDetails['phone'] ?? null;
        $address = $billingDetails['address'] ?? null;

        $customer = Customer::where('user_id', $user->id)->first();

        if ($customer) {
            $customer->update(array_filter([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
            ]));

            return $customer;
        }

        $providerCustomerId = $this->manager->driver($provider)->createCustomer($name, $email);

        return Customer::create([
            'user_id' => $user->id,
            'provider_customer_id' => $providerCustomerId,
            'email' => $email,
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
        ]);
    }

    private function ensurePaymentMethod(Customer $customer, string $providerId, string $provider): ?PaymentMethod
    {
        $data = $this->manager->driver($provider)->resolvePaymentMethod($providerId);

        if (! $data) {
            return null;
        }

        $existing = PaymentMethod::where('provider_payment_method_id', $data->providerPaymentMethodId)->first();

        if ($existing) {
            if (! $existing->is_default) {
                PaymentMethod::where('customer_id', $customer->id)->where('is_default', true)->update(['is_default' => false]);
                $existing->update(['is_default' => true]);
            }

            return $existing;
        }

        PaymentMethod::where('customer_id', $customer->id)->where('is_default', true)->update(['is_default' => false]);

        return PaymentMethod::create([
            'customer_id' => $customer->id,
            'provider_payment_method_id' => $data->providerPaymentMethodId,
            'type' => $data->type,
            'card_brand' => $data->cardBrand,
            'card_last_four' => $data->cardLastFour,
            'card_exp_month' => $data->cardExpMonth,
            'card_exp_year' => $data->cardExpYear,
            'is_default' => true,
        ]);
    }

    private function onCheckoutCompleted(WebhookData $webhook): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $webhook->payload;

        $session = CheckoutSession::where('provider_session_id', $payload['id'])->first();

        if (! $session) {
            return;
        }

        $session->update(['status' => CheckoutSessionStatus::Completed]);

        $customer = $session->customer;
        $subscriptionId = $payload['subscription'] ?? null;

        if ($subscriptionId) {
            $pm = $customer ? $this->ensurePaymentMethod($customer, $subscriptionId, $webhook->provider) : null;

            $subscription = Subscription::create([
                'customer_id' => $session->customer_id,
                'price_id' => $session->price_id,
                'payment_method_id' => $pm?->id,
                'provider_subscription_id' => $subscriptionId,
                'status' => SubscriptionStatus::Active,
                'current_period_starts_at' => now(),
            ]);

            event(new SubscriptionCreated($subscription));
        } elseif ($payload['payment_intent'] ?? null) {
            $pm = $customer ? $this->ensurePaymentMethod($customer, $payload['payment_intent'], $webhook->provider) : null;

            $payment = Payment::create([
                'customer_id' => $session->customer_id,
                'price_id' => $session->price_id,
                'payment_method_id' => $pm?->id,
                'provider_payment_id' => $payload['payment_intent'],
                'currency' => Currency::tryFrom(strtoupper($payload['currency'] ?? Currency::default())) ?? Currency::default(), // TODO: make it simpler
                'amount' => $payload['amount_total'] ?? 0,
                'status' => PaymentStatus::Succeeded,
            ]);

            event(new PaymentSucceeded($payment));
        }

        event(new CheckoutCompleted($session));
    }

    private function onSubscriptionUpdated(WebhookData $webhook): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $webhook->payload;

        $subscription = Subscription::where('provider_subscription_id', $payload['id'])->first();

        if (! $subscription) {
            Log::warning('Subscription not found for update', ['provider_subscription_id' => $payload['id']]);

            throw new \RuntimeException("Subscription not found: {$payload['id']}");
        }

        $status = match ($payload['status'] ?? null) {
            'active', 'trialing' => SubscriptionStatus::Active,
            'past_due', 'unpaid' => SubscriptionStatus::PastDue,
            'canceled', 'incomplete_expired' => SubscriptionStatus::Cancelled,
            'incomplete', 'paused' => SubscriptionStatus::Pending,
            default => $subscription->status,
        };

        $updates = ['status' => $status];

        if ($payload['default_payment_method'] ?? null) {
            $pm = $this->ensurePaymentMethod($subscription->customer, $payload['default_payment_method'], $webhook->provider);
            $updates['payment_method_id'] = $pm?->id;
        }

        if (isset($payload['current_period_start'])) {
            $updates['current_period_starts_at'] = Carbon::createFromTimestamp($payload['current_period_start']);
        }

        if (isset($payload['current_period_end'])) {
            $updates['current_period_ends_at'] = Carbon::createFromTimestamp($payload['current_period_end']);
        }

        if (isset($payload['cancel_at_period_end']) && $payload['cancel_at_period_end']) {
            $updates['cancelled_at'] = now();
            $endsAt = $payload['current_period_end'] ?? $payload['cancel_at'] ?? null;
            $updates['ends_at'] = $endsAt ? Carbon::createFromTimestamp($endsAt) : null;
        } elseif (isset($payload['cancel_at']) && $payload['cancel_at']) {
            $updates['cancelled_at'] = now();
            $updates['ends_at'] = Carbon::createFromTimestamp($payload['cancel_at']);
        } elseif (isset($payload['cancel_at_period_end']) && ! $payload['cancel_at_period_end'] && empty($payload['cancel_at'])) {
            $updates['cancelled_at'] = null;
            $updates['ends_at'] = null;
        }

        $subscription->update($updates);

        Log::info('Subscription updated', ['subscription_id' => $subscription->id, 'status' => $status->value]);

        event(new SubscriptionUpdated($subscription));
    }

    private function onSubscriptionDeleted(WebhookData $webhook): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $webhook->payload;

        $subscription = Subscription::where('provider_subscription_id', $payload['id'])->first();

        if (! $subscription) {
            Log::warning('Subscription not found for deletion', ['provider_subscription_id' => $payload['id']]);

            throw new \RuntimeException("Subscription not found: {$payload['id']}");
        }

        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'ends_at' => now(),
        ]);

        Log::info('Subscription cancelled', ['subscription_id' => $subscription->id]);

        event(new SubscriptionCancelled($subscription));
    }

    private function onPaymentSucceeded(WebhookData $webhook): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $webhook->payload;

        $customer = Customer::where('provider_customer_id', $payload['customer'] ?? null)->first();

        if (! $customer) {
            return;
        }

        $subscriptionId = $payload['subscription'] ?? $payload['parent']['subscription_details']['subscription'] ?? null;
        $subscription = $subscriptionId
            ? Subscription::where('provider_subscription_id', $subscriptionId)->first()
            : null;

        $pm = ($payload['default_payment_method'] ?? null)
            ? $this->ensurePaymentMethod($customer, $payload['default_payment_method'], $webhook->provider)
            : null;

        $payment = Payment::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription?->id,
            'payment_method_id' => $pm?->id,
            'price_id' => $subscription?->price_id,
            'provider_payment_id' => $payload['payment_intent'] ?? $payload['id'],
            'currency' => Currency::tryFrom(strtoupper($payload['currency'] ?? 'USD')) ?? Currency::USD,
            'amount' => $payload['amount_paid'] ?? 0,
            'status' => PaymentStatus::Succeeded,
        ]);

        event(new PaymentSucceeded($payment));
    }

    private function onPaymentFailed(WebhookData $webhook): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $webhook->payload;

        $customer = Customer::where('provider_customer_id', $payload['customer'] ?? null)->first();

        if (! $customer) {
            return;
        }

        $subscriptionId = $payload['subscription'] ?? $payload['parent']['subscription_details']['subscription'] ?? null;
        $subscription = $subscriptionId
            ? Subscription::where('provider_subscription_id', $subscriptionId)->first()
            : null;

        $pm = ($payload['default_payment_method'] ?? null)
            ? $this->ensurePaymentMethod($customer, $payload['default_payment_method'], $webhook->provider)
            : null;

        $payment = Payment::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription?->id,
            'payment_method_id' => $pm?->id,
            'provider_payment_id' => $payload['payment_intent'] ?? $payload['id'],
            'currency' => Currency::tryFrom(strtoupper($payload['currency'] ?? 'USD')) ?? Currency::USD,
            'amount' => $payload['amount_due'] ?? 0,
            'status' => PaymentStatus::Failed,
        ]);

        if ($subscription) {
            $subscription->update(['status' => SubscriptionStatus::PastDue]);
        }

        event(new PaymentFailed($payment));
    }

    private function onInvoicePaid(WebhookData $webhook): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $webhook->payload;

        $customer = Customer::where('provider_customer_id', $payload['customer'] ?? null)->first();

        if (! $customer) {
            return;
        }

        $subscriptionId = $payload['subscription'] ?? $payload['parent']['subscription_details']['subscription'] ?? null;
        $subscription = $subscriptionId
            ? Subscription::where('provider_subscription_id', $subscriptionId)->first()
            : null;

        $invoice = Invoice::updateOrCreate(
            ['provider_invoice_id' => $payload['id']],
            [
                'customer_id' => $customer->id,
                'subscription_id' => $subscription?->id,
                'number' => $payload['number'] ?? null,
                'currency' => Currency::tryFrom(strtoupper($payload['currency'] ?? 'USD')) ?? Currency::USD,
                'subtotal' => $payload['subtotal'] ?? 0,
                'tax' => $payload['tax'] ?? 0,
                'total' => $payload['total'] ?? 0,
                'status' => InvoiceStatus::Paid,
                'paid_at' => now(),
                'hosted_invoice_url' => $payload['hosted_invoice_url'] ?? null,
                'pdf_url' => $payload['invoice_pdf'] ?? null,
            ],
        );

        if ($subscription) {
            $lineItem = $payload['lines']['data'][0] ?? null;
            if ($lineItem && isset($lineItem['period'])) {
                $subscription->update([
                    'current_period_starts_at' => Carbon::createFromTimestamp($lineItem['period']['start']),
                    'current_period_ends_at' => Carbon::createFromTimestamp($lineItem['period']['end']),
                ]);
            }
        }

        event(new InvoicePaid($invoice));
    }
}
