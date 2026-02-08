<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Billing\Services\BillingService;

class WebhookController
{
    public function __construct(
        private BillingService $billingService,
    ) {}

    public function handle(string $provider, Request $request): Response
    {
        $this->billingService->handleWebhook($provider, $request);

        return response()->noContent(200);
    }
}
