<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Models\SubscriptionPlan;
use Modules\Billing\Models\SubscriptionPlanPrice;

class SubscriptionPlanPriceFactory extends Factory
{
    protected $model = SubscriptionPlanPrice::class;

    public function definition(): array
    {
        return [
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'provider_ids' => [
                'stripe' => 'price_'.$this->faker->regexify('[A-Za-z0-9]{14}'),
            ],
            'amount' => 9.99,
            'currency' => 'usd',
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that this is a yearly price.
     */
    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_interval' => 'year',
            'amount' => 99.99,
        ]);
    }

    /**
     * Indicate that the price is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
