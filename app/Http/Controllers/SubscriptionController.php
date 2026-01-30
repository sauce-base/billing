<?php

namespace Modules\Billing\Http\Controllers;

use Modules\Billing\Http\Requests\SubscribeRequest;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Models\SubscriptionPlanPrice;
use Modules\Billing\Services\SubscriptionService;

class SubscriptionController
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Create subscription.
     */
    public function store(SubscribeRequest $request)
    {
        $planPrice = SubscriptionPlanPrice::findOrFail($request->plan_price_id);

        try {
            $this->subscriptionService->subscribe(
                auth()->user(),
                $planPrice,
                $request->payment_method_id
            );

            return redirect()
                ->route('billing.index')
                ->with('success', 'Subscription created successfully!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel subscription.
     */
    public function destroy(Subscription $subscription)
    {
        $this->authorize('update', $subscription);

        $this->subscriptionService->cancel($subscription);

        return back()->with('success', 'Subscription canceled. Access until '.$subscription->ends_at->format('M d, Y'));
    }

    /**
     * Resume canceled subscription.
     */
    public function resume(Subscription $subscription)
    {
        $this->authorize('update', $subscription);

        try {
            $this->subscriptionService->resume($subscription);

            return back()->with('success', 'Subscription resumed!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
