<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Billing\Enums\CheckoutSessionStatus;
use Modules\Billing\Models\CheckoutSession;
use Modules\Billing\Services\BillingService;

class CheckoutController
{
    public function __construct(
        private BillingService $billingService,
    ) {}

    public function create(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'price_id' => ['required', 'exists:prices,id'],
        ]);

        $session = CheckoutSession::create([
            'price_id' => $validated['price_id'],
            'status' => CheckoutSessionStatus::Pending,
            'expires_at' => now()->addHours(24),
        ]);

        return redirect()->route('billing.checkout', $session);
    }

    public function show(CheckoutSession $checkoutSession): Response
    {
        $checkoutSession->load('price.product');

        return Inertia::render('Billing::Checkout', [
            'session' => $checkoutSession,
        ]);
    }

    public function store(Request $request, CheckoutSession $checkoutSession)
    {
        $user = $request->user();

        $rules = [];

        if (! $user) {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['email'] = ['required', 'email', 'max:255'];
        }

        $validated = $request->validate($rules);

        $name = $user ? $user->name : $validated['name'];
        $email = $user ? $user->email : $validated['email'];

        $result = $this->billingService->processCheckout(
            session: $checkoutSession,
            name: $name,
            email: $email,
        );

        return Inertia::location($result->url);
    }
}
