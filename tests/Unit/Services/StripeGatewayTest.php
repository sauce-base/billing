<?php

namespace Modules\Billing\Tests\Unit\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Modules\Billing\Exceptions\PaymentException;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Models\SubscriptionPlanPrice;
use Modules\Billing\Services\Gateways\StripeGateway;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected $stripeMock;

    protected $subscriptionService;

    protected $customerService;

    protected $paymentMethodService;

    protected $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable logging
        Config::set('billing.logging.enabled', false);

        // Mock Stripe client
        $this->stripeMock = Mockery::mock(StripeClient::class);
        $this->subscriptionService = Mockery::mock('Stripe\Service\SubscriptionService');
        $this->customerService = Mockery::mock('Stripe\Service\CustomerService');
        $this->paymentMethodService = Mockery::mock('Stripe\Service\PaymentMethodService');

        $this->stripeMock->subscriptions = $this->subscriptionService;
        $this->stripeMock->customers = $this->customerService;
        $this->stripeMock->paymentMethods = $this->paymentMethodService;

        // Create gateway and inject mock
        $this->gateway = new StripeGateway;
        $reflection = new \ReflectionClass($this->gateway);
        $property = $reflection->getProperty('stripe');
        $property->setAccessible(true);
        $property->setValue($this->gateway, $this->stripeMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function test_creates_subscription_for_new_customer(): void
    {
        $user = User::factory()->create(['stripe_customer_id' => null]);
        $planPrice = SubscriptionPlanPrice::factory()->create();

        $customerId = 'cus_test123';
        $subscriptionId = 'sub_test123';

        // Mock customer creation
        $this->customerService->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($args) use ($user) {
                return $args['email'] === $user->email &&
                       $args['name'] === $user->name &&
                       $args['payment_method'] === 'pm_test123';
            }))
            ->andReturn($this->mockStripeCustomer(['id' => $customerId]));

        // Mock subscription creation
        $this->subscriptionService->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($args) use ($customerId, $planPrice) {
                return $args['customer'] === $customerId &&
                       $args['items'][0]['price'] === $planPrice->stripe_price_id;
            }))
            ->andReturn($this->mockStripeSubscription(['id' => $subscriptionId]));

        $subscription = $this->gateway->createSubscription($user, $planPrice, 'pm_test123');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'subscription_plan_price_id' => $planPrice->id,
            'stripe_subscription_id' => $subscriptionId,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'stripe_customer_id' => $customerId,
        ]);
    }

    /** @test */
    public function test_creates_subscription_for_existing_customer(): void
    {
        $customerId = 'cus_existing123';
        $user = User::factory()->create(['stripe_customer_id' => $customerId]);
        $planPrice = SubscriptionPlanPrice::factory()->create();

        $subscriptionId = 'sub_test123';

        // Mock payment method attachment
        $this->paymentMethodService->shouldReceive('attach')
            ->once()
            ->with('pm_test123', ['customer' => $customerId])
            ->andReturn((object) ['id' => 'pm_test123']);

        // Mock subscription creation
        $this->subscriptionService->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($args) use ($customerId, $planPrice) {
                return $args['customer'] === $customerId &&
                       $args['items'][0]['price'] === $planPrice->stripe_price_id;
            }))
            ->andReturn($this->mockStripeSubscription(['id' => $subscriptionId]));

        $subscription = $this->gateway->createSubscription($user, $planPrice, 'pm_test123');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'stripe_subscription_id' => $subscriptionId,
        ]);
    }

    /** @test */
    public function test_creates_subscription_saves_correct_database_fields(): void
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_test123']);
        $planPrice = SubscriptionPlanPrice::factory()->create();

        $periodStart = now()->timestamp;
        $periodEnd = now()->addMonth()->timestamp;

        // Mock payment method attachment
        $this->paymentMethodService->shouldReceive('attach')
            ->once()
            ->andReturn((object) ['id' => 'pm_test123']);

        // Mock subscription creation with specific timestamps
        $this->subscriptionService->shouldReceive('create')
            ->once()
            ->andReturn($this->mockStripeSubscription([
                'id' => 'sub_test123',
                'status' => 'active',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
            ]));

        $subscription = $this->gateway->createSubscription($user, $planPrice, 'pm_test123');

        $this->assertEquals('active', $subscription->status);
        $this->assertEquals($user->id, $subscription->user_id);
        $this->assertEquals($planPrice->subscription_plan_id, $subscription->subscription_plan_id);
        $this->assertEquals($planPrice->id, $subscription->subscription_plan_price_id);
        $this->assertEquals('sub_test123', $subscription->stripe_subscription_id);
    }

    /** @test */
    public function test_creates_subscription_converts_timestamps_to_carbon(): void
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_test123']);
        $planPrice = SubscriptionPlanPrice::factory()->create();

        $periodStart = now()->timestamp;
        $periodEnd = now()->addMonth()->timestamp;

        // Mock payment method attachment
        $this->paymentMethodService->shouldReceive('attach')
            ->once()
            ->andReturn((object) ['id' => 'pm_test123']);

        // Mock subscription creation
        $this->subscriptionService->shouldReceive('create')
            ->once()
            ->andReturn($this->mockStripeSubscription([
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
            ]));

        $subscription = $this->gateway->createSubscription($user, $planPrice, 'pm_test123');

        $this->assertInstanceOf(Carbon::class, $subscription->current_period_start);
        $this->assertInstanceOf(Carbon::class, $subscription->current_period_end);
        $this->assertEquals($periodStart, $subscription->current_period_start->timestamp);
        $this->assertEquals($periodEnd, $subscription->current_period_end->timestamp);
    }

    /** @test */
    public function test_creates_subscription_handles_stripe_api_error(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test Stripe error');

        $user = User::factory()->create(['stripe_customer_id' => 'cus_test123']);
        $planPrice = SubscriptionPlanPrice::factory()->create();

        // Mock payment method attachment
        $this->paymentMethodService->shouldReceive('attach')
            ->once()
            ->andReturn((object) ['id' => 'pm_test123']);

        // Mock API error
        $this->subscriptionService->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Test Stripe error'));

        $this->gateway->createSubscription($user, $planPrice, 'pm_test123');
    }

    /** @test */
    public function test_cancels_subscription_at_period_end(): void
    {
        $subscription = Subscription::factory()->create([
            'stripe_subscription_id' => 'sub_test123',
            'status' => 'active',
        ]);

        $periodEnd = now()->addMonth()->timestamp;

        // Mock subscription update
        $this->subscriptionService->shouldReceive('update')
            ->once()
            ->with('sub_test123', ['cancel_at_period_end' => true])
            ->andReturn($this->mockStripeSubscription([
                'id' => 'sub_test123',
                'status' => 'active',
                'current_period_end' => $periodEnd,
            ]));

        $result = $this->gateway->cancelSubscription($subscription);

        $this->assertNotNull($result->canceled_at);
        $this->assertInstanceOf(Carbon::class, $result->ends_at);
        $this->assertEquals($periodEnd, $result->ends_at->timestamp);
    }

    /** @test */
    public function test_cancel_handles_stripe_api_error(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test cancel error');

        $subscription = Subscription::factory()->create([
            'stripe_subscription_id' => 'sub_test123',
        ]);

        // Mock API error
        $this->subscriptionService->shouldReceive('update')
            ->once()
            ->andThrow(new \Exception('Test cancel error'));

        $this->gateway->cancelSubscription($subscription);
    }

    /** @test */
    public function test_resumes_canceled_subscription_in_grace_period(): void
    {
        $subscription = Subscription::factory()->gracePeriod()->create([
            'stripe_subscription_id' => 'sub_test123',
        ]);

        // Mock subscription update
        $this->subscriptionService->shouldReceive('update')
            ->once()
            ->with('sub_test123', ['cancel_at_period_end' => false])
            ->andReturn($this->mockStripeSubscription([
                'id' => 'sub_test123',
                'status' => 'active',
            ]));

        $result = $this->gateway->resumeSubscription($subscription);

        $this->assertNull($result->canceled_at);
        $this->assertNull($result->ends_at);
        $this->assertEquals('active', $result->status);
    }

    /** @test */
    public function test_resume_throws_exception_for_expired_subscription(): void
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Cannot resume an expired subscription');

        $subscription = Subscription::factory()->expired()->create([
            'stripe_subscription_id' => 'sub_test123',
        ]);

        $this->gateway->resumeSubscription($subscription);
    }

    /** @test */
    public function test_resume_throws_exception_when_no_ends_at(): void
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Cannot resume an expired subscription');

        $subscription = Subscription::factory()->create([
            'stripe_subscription_id' => 'sub_test123',
            'ends_at' => null,
        ]);

        $this->gateway->resumeSubscription($subscription);
    }

    /** @test */
    public function test_verifies_webhook_signature_and_returns_parsed_data(): void
    {
        Config::set('services.stripe.webhook_secret', 'whsec_test123');

        $payload = '{"id":"evt_test123","type":"customer.subscription.created"}';
        $signature = 'test_signature';

        // Create a mock data object with toArray() method
        $dataMock = Mockery::mock();
        $dataMock->shouldReceive('toArray')
            ->once()
            ->andReturn(['object' => ['subscription_id' => 'sub_test123']]);

        // Mock Webhook::constructEvent using a partial mock
        $webhookMock = Mockery::mock('alias:\Stripe\Webhook');
        $webhookMock->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $signature, 'whsec_test123')
            ->andReturn((object) [
                'id' => 'evt_test123',
                'type' => 'customer.subscription.created',
                'data' => $dataMock,
            ]);

        $result = $this->gateway->verifyAndParseWebhook($payload, $signature);

        $this->assertEquals('evt_test123', $result['id']);
        $this->assertEquals('customer.subscription.created', $result['type']);
        $this->assertIsArray($result['data']);
        $this->assertEquals(['object' => ['subscription_id' => 'sub_test123']], $result['data']);
    }

    /** @test */
    public function test_webhook_verification_throws_exception_on_invalid_signature(): void
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        Config::set('services.stripe.webhook_secret', 'whsec_test123');

        $payload = '{"id":"evt_test123"}';
        $signature = 'invalid_signature';

        // Mock Webhook::constructEvent to throw exception
        $webhookMock = Mockery::mock('alias:\Stripe\Webhook');
        $webhookMock->shouldReceive('constructEvent')
            ->once()
            ->andThrow(new \Exception('Invalid signature'));

        $this->gateway->verifyAndParseWebhook($payload, $signature);
    }

    /**
     * Helper to create mock Stripe subscription response.
     */
    protected function mockStripeSubscription(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 'sub_test123',
            'status' => 'active',
            'current_period_start' => now()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
        ], $overrides);
    }

    /**
     * Helper to create mock Stripe customer response.
     */
    protected function mockStripeCustomer(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 'cus_test123',
            'email' => 'test@example.com',
        ], $overrides);
    }
}
