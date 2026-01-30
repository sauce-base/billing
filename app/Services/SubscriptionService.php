<?php

namespace Modules\Billing\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\PaymentGatewayInterface;
use Modules\Billing\Exceptions\PaymentException;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Models\SubscriptionPlanPrice;

class SubscriptionService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway
    ) {}

    /**
     * Subscribe user to a plan.
     */
    public function subscribe(
        User $user,
        SubscriptionPlanPrice $planPrice,
        string $paymentMethodId
    ): Subscription {
        return DB::transaction(function () use ($user, $planPrice, $paymentMethodId) {
            // Check if user already subscribed
            if ($user->hasActiveSubscription()) {
                throw PaymentException::alreadySubscribed();
            }

            return $this->gateway->createSubscription($user, $planPrice, $paymentMethodId);
        });
    }

    /**
     * Cancel user's subscription (at period end).
     */
    public function cancel(Subscription $subscription): Subscription
    {
        return $this->gateway->cancelSubscription($subscription);
    }

    /**
     * Resume canceled subscription.
     */
    public function resume(Subscription $subscription): Subscription
    {
        return $this->gateway->resumeSubscription($subscription);
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(Subscription $subscription): bool
    {
        return $subscription->status === 'active' &&
               (! $subscription->ends_at || $subscription->ends_at->isFuture());
    }
}
