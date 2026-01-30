<?php

namespace Modules\Billing\Http\Controllers;

use Inertia\Inertia;
use Modules\Billing\Models\Product;
use Modules\Billing\Models\SubscriptionPlan;
use Modules\Billing\Services\PaymentGatewayFactory;

class BillingController
{
    public function __construct(
        protected PaymentGatewayFactory $gatewayFactory
    ) {}

    /**
     * Show billing dashboard.
     */
    public function index()
    {
        // $user = auth()->user();

        return Inertia::render('Billing::Index', [
            'products' => Product::all(),
        ]);
    }

    /**
     * Show subscription plans.
     */
    public function plans()
    {
        $plans = SubscriptionPlan::with('prices')
            ->where('is_active', true)
            ->get();

        return Inertia::render('Billing::Plans', [
            'plans' => $plans,
            'publishable_key' => $this->getPublishableKey(),
        ])->withoutSSR();
    }

    /**
     * Get publishable key for the current payment gateway.
     */
    protected function getPublishableKey(): string
    {
        return $this->gatewayFactory->driver()->getPublishableKey();
    }
}
