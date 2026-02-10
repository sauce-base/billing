<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Contracts\PaymentGatewayInterface;
use Modules\Billing\Data\CheckoutResultData;
use Modules\Billing\Data\PaymentMethodData;
use Modules\Billing\Data\WebhookData;
use Modules\Billing\Enums\CheckoutSessionStatus;
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
use Modules\Billing\Models\Price;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Services\BillingService;
use Modules\Billing\Services\PaymentGatewayManager;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BillingService $billingService;

    /** @var PaymentGatewayInterface&\PHPUnit\Framework\MockObject\MockObject */
    private PaymentGatewayInterface $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = $this->createMock(PaymentGatewayInterface::class);
        $this->gateway->method('resolvePaymentMethod')->willReturn(
            new PaymentMethodData(
                providerPaymentMethodId: 'pm_test_123',
                type: 'card',
                cardBrand: 'visa',
                cardLastFour: '4242',
                cardExpMonth: 12,
                cardExpYear: 2030,
            ),
        );

        $manager = $this->createMock(PaymentGatewayManager::class);
        $manager->method('driver')->willReturn($this->gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);

        $this->billingService = $this->app->make(BillingService::class);
    }

    public function test_process_checkout_creates_customer(): void
    {
        $user = User::factory()->create();
        $price = Price::factory()->create();
        $session = CheckoutSession::create([
            'price_id' => $price->id,
            'status' => CheckoutSessionStatus::Pending,
            'expires_at' => now()->addHours(24),
        ]);

        $this->gateway->method('createCustomer')->willReturn('cus_guest_123');
        $this->gateway->method('createCheckoutSession')->willReturn(
            new CheckoutResultData(sessionId: 'cs_guest_123', url: 'https://stripe.com/checkout', provider: 'stripe'),
        );

        $billingDetails = [
            'name' => 'Billing Name',
            'email' => 'billing@example.com',
            'phone' => '+1234567890',
            'address' => [
                'street' => '123 Main St',
                'city' => 'Springfield',
                'state' => 'IL',
                'postal_code' => '62701',
                'country' => 'US',
            ],
        ];

        $result = $this->billingService->processCheckout($session, $user, 'https://example.com/success', 'https://example.com/cancel', $billingDetails);

        $this->assertEquals('cs_guest_123', $result->sessionId);
        $this->assertEquals('https://stripe.com/checkout', $result->url);

        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'provider_customer_id' => 'cus_guest_123',
            'name' => 'Billing Name',
            'email' => 'billing@example.com',
            'phone' => '+1234567890',
        ]);

        $session->refresh();
        $this->assertNotNull($session->customer_id);
        $this->assertEquals('cs_guest_123', $session->provider_session_id);
    }

    public function test_checkout_session_generates_uuid_automatically(): void
    {
        $price = Price::factory()->create();
        $session = CheckoutSession::create([
            'price_id' => $price->id,
            'status' => CheckoutSessionStatus::Pending,
        ]);

        $this->assertNotNull($session->uuid);
        $this->assertTrue(strlen($session->uuid) === 36);
    }

    public function test_checkout_session_uses_uuid_as_route_key(): void
    {
        $session = new CheckoutSession;
        $this->assertEquals('uuid', $session->getRouteKeyName());
    }

    public function test_cancel_delegates_to_gateway(): void
    {
        $subscription = Subscription::factory()->create();

        $this->gateway->expects($this->once())
            ->method('cancelSubscription')
            ->with($subscription, false);

        $this->billingService->cancel($subscription);
    }

    public function test_cancel_immediately_delegates_to_gateway(): void
    {
        $subscription = Subscription::factory()->create();

        $this->gateway->expects($this->once())
            ->method('cancelSubscription')
            ->with($subscription, true);

        $this->billingService->cancel($subscription, immediately: true);
    }

    public function test_webhook_checkout_completed_creates_subscription(): void
    {
        Event::fake([CheckoutCompleted::class, SubscriptionCreated::class]);

        $session = CheckoutSession::factory()->create([
            'provider_session_id' => 'cs_test_789',
        ]);

        $webhook = new WebhookData(
            type: WebhookEventType::CheckoutCompleted,
            provider: 'stripe',
            providerEventId: 'evt_test_1',
            payload: [
                'id' => 'cs_test_789',
                'subscription' => 'sub_test_123',
            ],
        );

        $this->gateway->method('verifyAndParseWebhook')->willReturn($webhook);

        $this->billingService->handleWebhook('stripe', request());

        $session->refresh();
        $this->assertEquals(CheckoutSessionStatus::Completed, $session->status);

        $subscription = Subscription::where('provider_subscription_id', 'sub_test_123')->first();
        $this->assertNotNull($subscription);
        $this->assertEquals($session->customer_id, $subscription->customer_id);
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
        $this->assertNotNull($subscription->payment_method_id);
        $this->assertDatabaseHas('payment_methods', [
            'provider_payment_method_id' => 'pm_test_123',
            'card_brand' => 'visa',
            'card_last_four' => '4242',
        ]);

        Event::assertDispatched(CheckoutCompleted::class);
        Event::assertDispatched(SubscriptionCreated::class);
    }

    public function test_webhook_checkout_completed_creates_payment_for_one_time_purchase(): void
    {
        Event::fake([CheckoutCompleted::class, PaymentSucceeded::class]);

        $session = CheckoutSession::factory()->create([
            'provider_session_id' => 'cs_test_onetime',
        ]);

        $webhook = new WebhookData(
            type: WebhookEventType::CheckoutCompleted,
            provider: 'stripe',
            providerEventId: 'evt_test_onetime',
            payload: [
                'id' => 'cs_test_onetime',
                'payment_intent' => 'pi_test_onetime',
                'currency' => 'usd',
                'amount_total' => 29900,
            ],
        );

        $this->gateway->method('verifyAndParseWebhook')->willReturn($webhook);

        $this->billingService->handleWebhook('stripe', request());

        $session->refresh();
        $this->assertEquals(CheckoutSessionStatus::Completed, $session->status);

        $payment = \Modules\Billing\Models\Payment::where('provider_payment_id', 'pi_test_onetime')->first();
        $this->assertNotNull($payment);
        $this->assertEquals($session->customer_id, $payment->customer_id);
        $this->assertEquals(29900, $payment->amount);
        $this->assertEquals(PaymentStatus::Succeeded, $payment->status);
        $this->assertNotNull($payment->payment_method_id);
        $this->assertDatabaseHas('payment_methods', [
            'provider_payment_method_id' => 'pm_test_123',
            'card_brand' => 'visa',
        ]);
        $this->assertDatabaseCount('subscriptions', 0);

        Event::assertDispatched(CheckoutCompleted::class);
        Event::assertDispatched(PaymentSucceeded::class);
    }

    public function test_webhook_subscription_updated_updates_status(): void
    {
        Event::fake([SubscriptionUpdated::class]);

        $subscription = Subscription::factory()->create([
            'provider_subscription_id' => 'sub_test_update',
            'status' => SubscriptionStatus::Active,
        ]);

        $webhook = new WebhookData(
            type: WebhookEventType::SubscriptionUpdated,
            provider: 'stripe',
            providerEventId: 'evt_test_2',
            payload: [
                'id' => 'sub_test_update',
                'status' => 'past_due',
                'default_payment_method' => 'pm_test_sub_update',
                'current_period_start' => 1700000000,
                'current_period_end' => 1702592000,
            ],
        );

        $this->gateway->method('verifyAndParseWebhook')->willReturn($webhook);

        $this->billingService->handleWebhook('stripe', request());

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::PastDue, $subscription->status);
        $this->assertNotNull($subscription->payment_method_id);
        $this->assertDatabaseHas('payment_methods', [
            'provider_payment_method_id' => 'pm_test_123',
            'card_brand' => 'visa',
            'card_last_four' => '4242',
        ]);

        Event::assertDispatched(SubscriptionUpdated::class);
    }

    public function test_webhook_subscription_deleted_cancels_subscription(): void
    {
        Event::fake([SubscriptionCancelled::class]);

        $subscription = Subscription::factory()->create([
            'provider_subscription_id' => 'sub_test_delete',
            'status' => SubscriptionStatus::Active,
        ]);

        $webhook = new WebhookData(
            type: WebhookEventType::SubscriptionDeleted,
            provider: 'stripe',
            providerEventId: 'evt_test_3',
            payload: [
                'id' => 'sub_test_delete',
            ],
        );

        $this->gateway->method('verifyAndParseWebhook')->willReturn($webhook);

        $this->billingService->handleWebhook('stripe', request());

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);

        Event::assertDispatched(SubscriptionCancelled::class);
    }

    public function test_webhook_payment_succeeded_creates_payment(): void
    {
        Event::fake([PaymentSucceeded::class]);

        $customer = Customer::factory()->create([
            'provider_customer_id' => 'cus_test_pay',
        ]);
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'provider_subscription_id' => 'sub_test_pay',
        ]);

        $webhook = new WebhookData(
            type: WebhookEventType::PaymentSucceeded,
            provider: 'stripe',
            providerEventId: 'evt_test_4',
            payload: [
                'id' => 'in_test_123',
                'customer' => 'cus_test_pay',
                'subscription' => 'sub_test_pay',
                'default_payment_method' => 'pm_test_pay',
                'payment_intent' => 'pi_test_123',
                'currency' => 'usd',
                'amount_paid' => 2900,
            ],
        );

        $this->gateway->method('verifyAndParseWebhook')->willReturn($webhook);

        $this->billingService->handleWebhook('stripe', request());

        $this->assertDatabaseHas('payment_methods', [
            'provider_payment_method_id' => 'pm_test_123',
            'customer_id' => $customer->id,
            'card_brand' => 'visa',
        ]);
        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'provider_payment_id' => 'pi_test_123',
            'amount' => 2900,
            'status' => PaymentStatus::Succeeded->value,
        ]);

        $payment = \Modules\Billing\Models\Payment::where('provider_payment_id', 'pi_test_123')->first();
        $this->assertNotNull($payment->payment_method_id);

        Event::assertDispatched(PaymentSucceeded::class);
    }

    public function test_webhook_payment_failed_marks_subscription_past_due(): void
    {
        Event::fake([PaymentFailed::class]);

        $customer = Customer::factory()->create([
            'provider_customer_id' => 'cus_test_fail',
        ]);
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'provider_subscription_id' => 'sub_test_fail',
            'status' => SubscriptionStatus::Active,
        ]);

        $webhook = new WebhookData(
            type: WebhookEventType::PaymentFailed,
            provider: 'stripe',
            providerEventId: 'evt_test_5',
            payload: [
                'id' => 'in_test_456',
                'customer' => 'cus_test_fail',
                'subscription' => 'sub_test_fail',
                'default_payment_method' => 'pm_test_fail',
                'payment_intent' => 'pi_test_456',
                'currency' => 'usd',
                'amount_due' => 2900,
            ],
        );

        $this->gateway->method('verifyAndParseWebhook')->willReturn($webhook);

        $this->billingService->handleWebhook('stripe', request());

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::PastDue, $subscription->status);

        $this->assertDatabaseHas('payment_methods', [
            'provider_payment_method_id' => 'pm_test_123',
            'customer_id' => $customer->id,
        ]);
        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'status' => PaymentStatus::Failed->value,
        ]);

        $payment = \Modules\Billing\Models\Payment::where('provider_payment_id', 'pi_test_456')->first();
        $this->assertNotNull($payment->payment_method_id);

        Event::assertDispatched(PaymentFailed::class);
    }

    public function test_webhook_invoice_paid_creates_invoice(): void
    {
        Event::fake([InvoicePaid::class]);

        $customer = Customer::factory()->create([
            'provider_customer_id' => 'cus_test_inv',
        ]);

        $webhook = new WebhookData(
            type: WebhookEventType::InvoicePaid,
            provider: 'stripe',
            providerEventId: 'evt_test_6',
            payload: [
                'id' => 'in_test_invoice',
                'customer' => 'cus_test_inv',
                'number' => 'INV-001',
                'currency' => 'usd',
                'subtotal' => 2900,
                'tax' => 0,
                'total' => 2900,
                'hosted_invoice_url' => 'https://stripe.com/invoice/123',
                'invoice_pdf' => 'https://stripe.com/invoice/123/pdf',
            ],
        );

        $this->gateway->method('verifyAndParseWebhook')->willReturn($webhook);

        $this->billingService->handleWebhook('stripe', request());

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'provider_invoice_id' => 'in_test_invoice',
            'number' => 'INV-001',
            'total' => 2900,
        ]);

        Event::assertDispatched(InvoicePaid::class);
    }
}
