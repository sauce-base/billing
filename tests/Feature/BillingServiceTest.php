<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Contracts\PaymentGatewayInterface;
use Modules\Billing\Data\CheckoutResultData;
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
use Modules\Billing\Services\PaymentGatewayFactory;
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
        $this->app->instance(PaymentGatewayInterface::class, $this->gateway);

        $this->billingService = $this->app->make(BillingService::class);
    }

    public function test_checkout_creates_customer_if_missing(): void
    {
        Event::fake([CheckoutCompleted::class]);

        $user = User::factory()->create();
        $price = Price::factory()->create();

        $this->gateway->method('createCustomer')->willReturn('cus_test_123');
        $this->gateway->method('createCheckoutSession')->willReturn(
            new CheckoutResultData(sessionId: 'cs_test_123', url: 'https://stripe.com/checkout', provider: 'stripe'),
        );

        $result = $this->billingService->checkout($user, $price, 'https://example.com/success', 'https://example.com/cancel');

        $this->assertEquals('cs_test_123', $result->sessionId);
        $this->assertEquals('https://stripe.com/checkout', $result->url);
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'provider_customer_id' => 'cus_test_123',
        ]);
        $this->assertDatabaseHas('checkout_sessions', [
            'provider_session_id' => 'cs_test_123',
            'status' => CheckoutSessionStatus::Pending->value,
        ]);
    }

    public function test_process_checkout_creates_user_and_customer(): void
    {
        Event::fake([CheckoutCompleted::class]);

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

        $result = $this->billingService->processCheckout($session, 'John Doe', 'john@example.com');

        $this->assertEquals('cs_guest_123', $result->sessionId);
        $this->assertEquals('https://stripe.com/checkout', $result->url);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertNotNull($user->email_verified_at);

        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'provider_customer_id' => 'cus_guest_123',
            'email' => 'john@example.com',
        ]);

        $session->refresh();
        $this->assertNotNull($session->customer_id);
        $this->assertEquals('cs_guest_123', $session->provider_session_id);
    }

    public function test_process_checkout_reuses_existing_user(): void
    {
        Event::fake([CheckoutCompleted::class]);

        $existingUser = User::factory()->create(['email' => 'existing@example.com']);
        $price = Price::factory()->create();
        $session = CheckoutSession::create([
            'price_id' => $price->id,
            'status' => CheckoutSessionStatus::Pending,
            'expires_at' => now()->addHours(24),
        ]);

        $this->gateway->method('createCustomer')->willReturn('cus_existing_123');
        $this->gateway->method('createCheckoutSession')->willReturn(
            new CheckoutResultData(sessionId: 'cs_existing_123', url: 'https://stripe.com/checkout', provider: 'stripe'),
        );

        $this->billingService->processCheckout($session, 'Any Name', 'existing@example.com');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('customers', [
            'user_id' => $existingUser->id,
        ]);
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

    public function test_checkout_reuses_existing_customer(): void
    {
        Event::fake([CheckoutCompleted::class]);

        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $price = Price::factory()->create();

        $this->gateway->expects($this->never())->method('createCustomer');
        $this->gateway->method('createCheckoutSession')->willReturn(
            new CheckoutResultData(sessionId: 'cs_test_456', url: 'https://stripe.com/checkout', provider: 'stripe'),
        );

        $result = $this->billingService->checkout($user, $price, 'https://example.com/success', 'https://example.com/cancel');

        $this->assertEquals('cs_test_456', $result->sessionId);
        $this->assertDatabaseCount('customers', 1);
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

        $mockGateway = $this->createMock(PaymentGatewayInterface::class);
        $mockGateway->method('verifyAndParseWebhook')->willReturn($webhook);

        $factory = $this->createMock(PaymentGatewayFactory::class);
        $factory->method('driver')->willReturn($mockGateway);
        $this->app->instance(PaymentGatewayFactory::class, $factory);

        $billingService = $this->app->make(BillingService::class);
        $billingService->handleWebhook('stripe', request());

        $session->refresh();
        $this->assertEquals(CheckoutSessionStatus::Completed, $session->status);
        $this->assertDatabaseHas('subscriptions', [
            'provider_subscription_id' => 'sub_test_123',
            'customer_id' => $session->customer_id,
            'status' => SubscriptionStatus::Active->value,
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

        $mockGateway = $this->createMock(PaymentGatewayInterface::class);
        $mockGateway->method('verifyAndParseWebhook')->willReturn($webhook);

        $factory = $this->createMock(PaymentGatewayFactory::class);
        $factory->method('driver')->willReturn($mockGateway);
        $this->app->instance(PaymentGatewayFactory::class, $factory);

        $billingService = $this->app->make(BillingService::class);
        $billingService->handleWebhook('stripe', request());

        $session->refresh();
        $this->assertEquals(CheckoutSessionStatus::Completed, $session->status);
        $this->assertDatabaseHas('payments', [
            'customer_id' => $session->customer_id,
            'price_id' => $session->price_id,
            'provider_payment_id' => 'pi_test_onetime',
            'amount' => 29900,
            'status' => PaymentStatus::Succeeded->value,
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
                'current_period_start' => 1700000000,
                'current_period_end' => 1702592000,
            ],
        );

        $mockGateway = $this->createMock(PaymentGatewayInterface::class);
        $mockGateway->method('verifyAndParseWebhook')->willReturn($webhook);

        $factory = $this->createMock(PaymentGatewayFactory::class);
        $factory->method('driver')->willReturn($mockGateway);
        $this->app->instance(PaymentGatewayFactory::class, $factory);

        $billingService = $this->app->make(BillingService::class);
        $billingService->handleWebhook('stripe', request());

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::PastDue, $subscription->status);

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

        $mockGateway = $this->createMock(PaymentGatewayInterface::class);
        $mockGateway->method('verifyAndParseWebhook')->willReturn($webhook);

        $factory = $this->createMock(PaymentGatewayFactory::class);
        $factory->method('driver')->willReturn($mockGateway);
        $this->app->instance(PaymentGatewayFactory::class, $factory);

        $billingService = $this->app->make(BillingService::class);
        $billingService->handleWebhook('stripe', request());

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
                'payment_intent' => 'pi_test_123',
                'currency' => 'usd',
                'amount_paid' => 2900,
            ],
        );

        $mockGateway = $this->createMock(PaymentGatewayInterface::class);
        $mockGateway->method('verifyAndParseWebhook')->willReturn($webhook);

        $factory = $this->createMock(PaymentGatewayFactory::class);
        $factory->method('driver')->willReturn($mockGateway);
        $this->app->instance(PaymentGatewayFactory::class, $factory);

        $billingService = $this->app->make(BillingService::class);
        $billingService->handleWebhook('stripe', request());

        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'provider_payment_id' => 'pi_test_123',
            'amount' => 2900,
            'status' => PaymentStatus::Succeeded->value,
        ]);

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
                'payment_intent' => 'pi_test_456',
                'currency' => 'usd',
                'amount_due' => 2900,
            ],
        );

        $mockGateway = $this->createMock(PaymentGatewayInterface::class);
        $mockGateway->method('verifyAndParseWebhook')->willReturn($webhook);

        $factory = $this->createMock(PaymentGatewayFactory::class);
        $factory->method('driver')->willReturn($mockGateway);
        $this->app->instance(PaymentGatewayFactory::class, $factory);

        $billingService = $this->app->make(BillingService::class);
        $billingService->handleWebhook('stripe', request());

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::PastDue, $subscription->status);

        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'status' => PaymentStatus::Failed->value,
        ]);

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

        $mockGateway = $this->createMock(PaymentGatewayInterface::class);
        $mockGateway->method('verifyAndParseWebhook')->willReturn($webhook);

        $factory = $this->createMock(PaymentGatewayFactory::class);
        $factory->method('driver')->willReturn($mockGateway);
        $this->app->instance(PaymentGatewayFactory::class, $factory);

        $billingService = $this->app->make(BillingService::class);
        $billingService->handleWebhook('stripe', request());

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'provider_invoice_id' => 'in_test_invoice',
            'number' => 'INV-001',
            'total' => 2900,
        ]);

        Event::assertDispatched(InvoicePaid::class);
    }
}
