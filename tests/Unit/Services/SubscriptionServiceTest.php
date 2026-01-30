<?php

namespace Modules\Billing\Tests\Unit\Services;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Billing\Contracts\PaymentGatewayInterface;
use Modules\Billing\Exceptions\PaymentException;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Models\SubscriptionPlanPrice;
use Modules\Billing\Services\SubscriptionService;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $gatewayMock;

    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $this->service = new SubscriptionService($this->gatewayMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function test_subscribes_user_to_plan(): void
    {
        $user = User::factory()->create();
        $planPrice = SubscriptionPlanPrice::factory()->create();
        $subscription = Subscription::factory()->make([
            'user_id' => $user->id,
            'subscription_plan_price_id' => $planPrice->id,
        ]);

        $this->gatewayMock->shouldReceive('createSubscription')
            ->once()
            ->with($user, $planPrice, 'pm_test123')
            ->andReturn($subscription);

        $result = $this->service->subscribe($user, $planPrice, 'pm_test123');

        $this->assertEquals($user->id, $result->user_id);
        $this->assertEquals($planPrice->id, $result->subscription_plan_price_id);
    }

    /** @test */
    public function test_subscribe_throws_exception_if_user_already_subscribed(): void
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('User already has an active subscription');

        $user = User::factory()->create();
        $planPrice = SubscriptionPlanPrice::factory()->create();

        // Create an active subscription for the user
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'ends_at' => null,
        ]);

        $this->service->subscribe($user, $planPrice, 'pm_test123');
    }

    /** @test */
    public function test_subscribe_uses_database_transaction(): void
    {
        $user = User::factory()->create();
        $planPrice = SubscriptionPlanPrice::factory()->create();

        // Mock gateway to throw exception to trigger rollback
        $this->gatewayMock->shouldReceive('createSubscription')
            ->once()
            ->andThrow(new \Exception('Stripe error'));

        try {
            $this->service->subscribe($user, $planPrice, 'pm_test123');
        } catch (\Exception $e) {
            // Expected exception
        }

        // Verify no subscription was created (transaction rolled back)
        $this->assertDatabaseCount('subscriptions', 0);
    }

    /** @test */
    public function test_subscribe_calls_gateway_with_correct_parameters(): void
    {
        $user = User::factory()->create();
        $planPrice = SubscriptionPlanPrice::factory()->create();
        $subscription = Subscription::factory()->make();

        $this->gatewayMock->shouldReceive('createSubscription')
            ->once()
            ->with(
                Mockery::on(fn ($arg) => $arg->id === $user->id),
                Mockery::on(fn ($arg) => $arg->id === $planPrice->id),
                'pm_test123'
            )
            ->andReturn($subscription);

        $this->service->subscribe($user, $planPrice, 'pm_test123');
    }

    /** @test */
    public function test_cancels_subscription(): void
    {
        $subscription = Subscription::factory()->create();
        $canceledSubscription = Subscription::factory()->canceled()->make();

        $this->gatewayMock->shouldReceive('cancelSubscription')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->id === $subscription->id))
            ->andReturn($canceledSubscription);

        $result = $this->service->cancel($subscription);

        $this->assertNotNull($result->canceled_at);
    }

    /** @test */
    public function test_resumes_subscription(): void
    {
        $subscription = Subscription::factory()->gracePeriod()->create();
        $resumedSubscription = Subscription::factory()->make([
            'canceled_at' => null,
            'ends_at' => null,
        ]);

        $this->gatewayMock->shouldReceive('resumeSubscription')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->id === $subscription->id))
            ->andReturn($resumedSubscription);

        $result = $this->service->resume($subscription);

        $this->assertNull($result->canceled_at);
        $this->assertNull($result->ends_at);
    }

    /** @test */
    public function test_is_active_returns_true_for_active_subscription_without_end_date(): void
    {
        $subscription = Subscription::factory()->make([
            'status' => 'active',
            'ends_at' => null,
        ]);

        $result = $this->service->isActive($subscription);

        $this->assertTrue($result);
    }

    /** @test */
    public function test_is_active_returns_false_for_expired_subscription(): void
    {
        $subscription = Subscription::factory()->make([
            'status' => 'active',
            'ends_at' => now()->subDay(),
        ]);

        $result = $this->service->isActive($subscription);

        $this->assertFalse($result);
    }
}
