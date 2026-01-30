<?php

namespace Modules\Billing\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Models\SubscriptionPlan;
use Modules\Billing\Models\SubscriptionPlanPrice;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'subscription_plan_price_id' => SubscriptionPlanPrice::factory(),
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_'.$this->faker->regexify('[A-Za-z0-9]{24}'),
            'provider_metadata' => [
                'customer_id' => 'cus_'.$this->faker->regexify('[A-Za-z0-9]{14}'),
            ],
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'canceled_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * Indicate that the subscription has been canceled but is still in grace period.
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'canceled_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    /**
     * Indicate that the subscription is in grace period (canceled with future end date).
     */
    public function gracePeriod(): static
    {
        return $this->state(fn (array $attributes) => [
            'canceled_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
        ]);
    }

    /**
     * Indicate that the subscription has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'canceled_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'status' => 'canceled',
        ]);
    }
}
