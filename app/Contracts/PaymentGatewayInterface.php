<?php

namespace Modules\Billing\Contracts;

use Illuminate\Http\Request;
use Modules\Billing\Data\CheckoutData;
use Modules\Billing\Data\CheckoutResultData;
use Modules\Billing\Data\PaymentMethodData;
use Modules\Billing\Data\WebhookData;
use Modules\Billing\Models\Customer;
use Modules\Billing\Models\Subscription;

interface PaymentGatewayInterface
{
    public function createCustomer(string $name, string $email): string;

    public function createCheckoutSession(CheckoutData $data): CheckoutResultData;

    public function cancelSubscription(Subscription $subscription, bool $immediately = false): void;

    public function getManagementUrl(Customer $customer): string;

    public function verifyAndParseWebhook(Request $request): WebhookData;

    public function resolvePaymentMethod(string $providerId): ?PaymentMethodData;
}
