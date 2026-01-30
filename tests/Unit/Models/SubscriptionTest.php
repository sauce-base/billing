<?php

namespace Modules\Billing\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Models\Subscription;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_is_active_returns_true_for_active_subscription_without_end_date(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => 'active',
            'ends_at' => null,
        ]);

        $this->assertTrue($subscription->isActive());
    }

    /** @test */
    public function test_is_active_returns_true_for_active_subscription_with_future_end_date(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => 'active',
            'ends_at' => now()->addDays(10),
        ]);

        $this->assertTrue($subscription->isActive());
    }

    /** @test */
    public function test_is_active_returns_false_for_inactive_or_expired_subscription(): void
    {
        // Test with canceled status
        $canceledSubscription = Subscription::factory()->create([
            'status' => 'canceled',
            'ends_at' => null,
        ]);
        $this->assertFalse($canceledSubscription->isActive());

        // Test with expired end date
        $expiredSubscription = Subscription::factory()->create([
            'status' => 'active',
            'ends_at' => now()->subDay(),
        ]);
        $this->assertFalse($expiredSubscription->isActive());

        // Test with both canceled and expired
        $bothSubscription = Subscription::factory()->create([
            'status' => 'canceled',
            'ends_at' => now()->subDay(),
        ]);
        $this->assertFalse($bothSubscription->isActive());
    }

    /** @test */
    public function test_is_canceled_returns_true_when_canceled_at_set(): void
    {
        $subscription = Subscription::factory()->canceled()->create();

        $this->assertTrue($subscription->isCanceled());
        $this->assertNotNull($subscription->canceled_at);
    }

    /** @test */
    public function test_is_canceled_returns_false_when_canceled_at_null(): void
    {
        $subscription = Subscription::factory()->create([
            'canceled_at' => null,
        ]);

        $this->assertFalse($subscription->isCanceled());
    }

    /** @test */
    public function test_on_grace_period_returns_true_when_canceled_with_future_end_date(): void
    {
        $subscription = Subscription::factory()->gracePeriod()->create();

        $this->assertTrue($subscription->onGracePeriod());
        $this->assertNotNull($subscription->canceled_at);
        $this->assertTrue($subscription->ends_at->isFuture());
    }

    /** @test */
    public function test_on_grace_period_returns_false_when_not_canceled(): void
    {
        $subscription = Subscription::factory()->create([
            'canceled_at' => null,
            'ends_at' => now()->addMonth(),
        ]);

        $this->assertFalse($subscription->onGracePeriod());
    }

    /** @test */
    public function test_on_grace_period_returns_false_when_end_date_past(): void
    {
        $subscription = Subscription::factory()->expired()->create();

        $this->assertFalse($subscription->onGracePeriod());
        $this->assertNotNull($subscription->canceled_at);
        $this->assertTrue($subscription->ends_at->isPast());
    }

    /** @test */
    public function test_on_grace_period_returns_false_when_no_end_date(): void
    {
        $subscription = Subscription::factory()->create([
            'canceled_at' => now(),
            'ends_at' => null,
        ]);

        $this->assertFalse($subscription->onGracePeriod());
    }
}
