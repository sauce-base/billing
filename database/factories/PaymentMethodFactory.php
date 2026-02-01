<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Models\Customer;
use Modules\Billing\Models\PaymentMethod;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Billing\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'provider_payment_method_id' => 'pm_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'type' => 'card',
            'card_brand' => fake()->randomElement(['visa', 'mastercard', 'amex']),
            'card_last_four' => fake()->numerify('####'),
            'card_exp_month' => fake()->numberBetween(1, 12),
            'card_exp_year' => now()->addYears(3)->year,
            'metadata' => null,
            'is_default' => false,
        ];
    }

    /**
     * Indicate that this is the default payment method.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Indicate that the card is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_exp_month' => 1,
            'card_exp_year' => now()->subYear()->year,
        ]);
    }

    /**
     * Indicate that the card is a Visa.
     */
    public function visa(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_brand' => 'visa',
        ]);
    }

    /**
     * Indicate that the card is a Mastercard.
     */
    public function mastercard(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_brand' => 'mastercard',
        ]);
    }
}
