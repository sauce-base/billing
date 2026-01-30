<?php

namespace Modules\Billing\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Models\SubscriptionPlan;
use Modules\Billing\Models\SubscriptionPlanPrice;
use Tests\TestCase;

class SubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_monthly_price_returns_monthly_billing_interval(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        // Create monthly price
        $monthlyPrice = SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $plan->id,
            'billing_interval' => 'month',
            'amount' => 9.99,
        ]);

        // Create yearly price to ensure filtering works
        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $plan->id,
            'billing_interval' => 'year',
            'amount' => 99.99,
        ]);

        $result = $plan->monthlyPrice();

        $this->assertNotNull($result);
        $this->assertEquals('month', $result->billing_interval);
        $this->assertEquals($monthlyPrice->id, $result->id);
        $this->assertEquals(9.99, $result->amount);
    }

    /** @test */
    public function test_yearly_price_returns_yearly_billing_interval(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        // Create yearly price
        $yearlyPrice = SubscriptionPlanPrice::factory()->yearly()->create([
            'subscription_plan_id' => $plan->id,
        ]);

        // Create monthly price to ensure filtering works
        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $plan->id,
            'billing_interval' => 'month',
            'amount' => 9.99,
        ]);

        $result = $plan->yearlyPrice();

        $this->assertNotNull($result);
        $this->assertEquals('year', $result->billing_interval);
        $this->assertEquals($yearlyPrice->id, $result->id);
    }
}
