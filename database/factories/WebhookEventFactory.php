<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Models\WebhookEvent;

class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        return [
            'provider' => 'stripe',
            'event_id' => 'evt_'.$this->faker->regexify('[A-Za-z0-9]{24}'),
            'type' => $this->faker->randomElement([
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted',
                'invoice.payment_succeeded',
            ]),
            'payload' => [
                'id' => 'evt_'.$this->faker->regexify('[A-Za-z0-9]{24}'),
                'object' => 'event',
                'data' => [
                    'object' => [
                        'id' => 'sub_'.$this->faker->regexify('[A-Za-z0-9]{24}'),
                    ],
                ],
            ],
            'processed_at' => null,
        ];
    }

    /**
     * Indicate that the webhook event has been processed.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed_at' => now(),
        ]);
    }
}
