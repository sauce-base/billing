<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Models\SubscriptionPlan;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->slug(2),
            'description' => $this->faker->sentence(),
            'provider_ids' => [
                'stripe' => 'prod_'.$this->faker->regexify('[A-Za-z0-9]{14}'),
            ],
            'features' => [
                'feature_1' => $this->faker->sentence(3),
                'feature_2' => $this->faker->sentence(3),
                'feature_3' => $this->faker->sentence(3),
            ],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the plan is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
