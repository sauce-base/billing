<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Billing\Services\BillingService;

class BillingPortalController
{
    public function __construct(
        private BillingService $billingService,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $url = $this->billingService->getManagementUrl($user);

        return redirect()->away($url);
    }
}
