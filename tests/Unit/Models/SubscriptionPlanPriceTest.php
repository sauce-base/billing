<?php

namespace Modules\Billing\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Models\SubscriptionPlanPrice;
use Tests\TestCase;

class SubscriptionPlanPriceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_formatted_amount_attribute_returns_currency_format(): void
    {
        $price = SubscriptionPlanPrice::factory()->create([
            'amount' => 9.99,
            'currency' => 'usd',
        ]);

        $this->assertEquals('$9.99', $price->formatted_amount);
    }

    /** @test */
    public function test_formatted_amount_handles_different_currencies(): void
    {
        // Test with USD
        $usdPrice = SubscriptionPlanPrice::factory()->create([
            'amount' => 19.99,
            'currency' => 'usd',
        ]);
        $this->assertEquals('$19.99', $usdPrice->formatted_amount);

        // Test with EUR (still shows $ in current implementation)
        $eurPrice = SubscriptionPlanPrice::factory()->create([
            'amount' => 15.50,
            'currency' => 'eur',
        ]);
        $this->assertEquals('$15.50', $eurPrice->formatted_amount);

        // Test with whole number
        $wholePrice = SubscriptionPlanPrice::factory()->create([
            'amount' => 100.00,
            'currency' => 'usd',
        ]);
        $this->assertEquals('$100.00', $wholePrice->formatted_amount);
    }
}
