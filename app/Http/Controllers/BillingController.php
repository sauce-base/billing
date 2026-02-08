<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Product;

class BillingController
{
    public function index(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $subscription = $user->billingCustomer
            ?->subscriptions()
            ->with(['price.product'])
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->latest()
            ->first();

        $purchase = $user->billingCustomer
            ?->payments()
            ->whereNull('subscription_id')
            ->where('status', PaymentStatus::Succeeded)
            ->with(['price.product'])
            ->latest()
            ->first();

        return Inertia::render('Billing::Index', [
            'products' => Product::displayable()->get(),
            'subscription' => $subscription,
            'purchase' => $purchase,
        ]);
    }
}
