<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Services\BillingService;

class BillingPortalController
{
    public function __construct(
        private BillingService $billingService,
    ) {}

    public function __invoke(): RedirectResponse
    {
        $url = $this->billingService->getManagementUrl(Auth::user());

        return redirect()->away($url);
    }
}
