<?php

namespace Modules\Billing\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
use Modules\Billing\Models\Price;
use Modules\Billing\Models\Subscription;

class BillingService
{
    public function __construct(
        private PaymentGatewayManager $manager,
    ) {}

    public function checkout(User $user, Price $price, string $successUrl, string $cancelUrl): CheckoutResultData
    {
        $customer = $this->ensureCustomer($user);

        $data = new CheckoutData(
            customer: $customer,
            price: $price,
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
        );

        $result = $this->manager->driver()->createCheckoutSession($data);

        CheckoutSession::create([
            'customer_id' => $customer->id,
            'price_id' => $price->id,
            'provider_session_id' => $result->sessionId,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'status' => CheckoutSessionStatus::Pending,
        ]);

        return $result;
    }

    public function processCheckout(CheckoutSession $session, string $name, string $email): CheckoutResultData
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
            ],
        );

        $customer = $this->ensureCustomer($user);
        $price = $session->price()->with('product')->firstOrFail();

        $successUrl = auth()->check() ? route('billing.index') : route('index');
        $cancelUrl = route('billing.checkout', $session);

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

    public function cancel(Subscription $subscription, bool $immediately = false): void
    {
        $this->manager->driver()->cancelSubscription($subscription, $immediately);
    }

    public function getManagementUrl(User $user): string
    {
        $customer = Customer::where('user_id', $user->id)->firstOrFail();

        return $this->manager->driver()->getManagementUrl($customer);
    }

    private function ensureCustomer(User $user): Customer
    {
        $customer = Customer::where('user_id', $user->id)->first();

        if ($customer) {
            return $customer;
        }

        $providerCustomerId = $this->manager->driver()->createCustomer($user->name, $user->email);

        return Customer::create([
            'user_id' => $user->id,
            'provider_customer_id' => $providerCustomerId,
            'email' => $user->email,
            'name' => $user->name,
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

        $subscriptionId = $payload['subscription'] ?? null;

        if ($subscriptionId) {
            $subscription = Subscription::create([
                'customer_id' => $session->customer_id,
                'price_id' => $session->price_id,
                'provider_subscription_id' => $subscriptionId,
                'status' => SubscriptionStatus::Active,
                'current_period_starts_at' => now(),
            ]);

            event(new SubscriptionCreated($subscription));
        } elseif ($payload['payment_intent'] ?? null) {
            $payment = Payment::create([
                'customer_id' => $session->customer_id,
                'price_id' => $session->price_id,
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
            return;
        }

        $status = match ($payload['status'] ?? null) {
            'active' => SubscriptionStatus::Active,
            'past_due' => SubscriptionStatus::PastDue,
            'canceled' => SubscriptionStatus::Cancelled,
            default => $subscription->status,
        };

        $updates = ['status' => $status];

        if (isset($payload['current_period_start'])) {
            $updates['current_period_starts_at'] = Carbon::createFromTimestamp($payload['current_period_start']);
        }

        if (isset($payload['current_period_end'])) {
            $updates['current_period_ends_at'] = Carbon::createFromTimestamp($payload['current_period_end']);
        }

        if (isset($payload['cancel_at_period_end']) && $payload['cancel_at_period_end']) {
            $updates['cancelled_at'] = now();
            $updates['ends_at'] = isset($payload['current_period_end'])
                ? Carbon::createFromTimestamp($payload['current_period_end'])
                : null;
        }

        $subscription->update($updates);

        event(new SubscriptionUpdated($subscription));
    }

    private function onSubscriptionDeleted(WebhookData $webhook): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $webhook->payload;

        $subscription = Subscription::where('provider_subscription_id', $payload['id'])->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'ends_at' => now(),
        ]);

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

        $subscription = isset($payload['subscription'])
            ? Subscription::where('provider_subscription_id', $payload['subscription'])->first()
            : null;

        $payment = Payment::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription?->id,
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

        $subscription = isset($payload['subscription'])
            ? Subscription::where('provider_subscription_id', $payload['subscription'])->first()
            : null;

        $payment = Payment::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription?->id,
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

        $subscription = isset($payload['subscription'])
            ? Subscription::where('provider_subscription_id', $payload['subscription'])->first()
            : null;

        Invoice::updateOrCreate(
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

        $invoice = Invoice::where('provider_invoice_id', $payload['id'])->first();

        if ($invoice) {
            event(new InvoicePaid($invoice));
        }
    }
}
