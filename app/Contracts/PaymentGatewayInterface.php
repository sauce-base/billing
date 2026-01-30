<?php

namespace Modules\Billing\Contracts;

use App\Models\User;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Models\SubscriptionPlanPrice;

interface PaymentGatewayInterface
{
    /**
     * Get the gateway identifier name.
     */
    public function getName(): string;

    /**
     * Get the publishable/public key for this gateway.
     */
    public function getPublishableKey(): string;

    /**
     * Create a subscription for a user.
     */
    public function createSubscription(
        User $user,
        SubscriptionPlanPrice $planPrice,
        string $paymentMethodId
    ): Subscription;

    /**
     * Cancel subscription (at period end).
     */
    public function cancelSubscription(Subscription $subscription): Subscription;

    /**
     * Resume a canceled subscription (if still in grace period).
     */
    public function resumeSubscription(Subscription $subscription): Subscription;

    /**
     * Verify webhook signature and parse event.
     *
     * @return array{id: string, type: string, data: array}
     */
    public function verifyAndParseWebhook(string $payload, string $signature): array;
}
