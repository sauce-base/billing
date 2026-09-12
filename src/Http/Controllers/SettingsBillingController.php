<?php

namespace Modules\Billing\Http\Controllers;

use App\Settings\SettingsSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Billing\Services\BillingService;

class SettingsBillingController
{
    /**
     * Land the visitor on the billing section of the settings modal.
     *
     * Stripe returns here with a `session_id` after checkout, so the fulfilment
     * still has to run server-side before the panel is shown. The section itself
     * lives behind the `#settings/billing` fragment, which never reaches the
     * server.
     */
    public function show(Request $request, BillingService $billingService): RedirectResponse
    {
        if ($sessionId = $request->query('session_id')) {
            $billingService->fulfillCheckoutIfNeeded($sessionId);
        }

        return redirect()->to(SettingsSection::url('billing'));
    }
}
