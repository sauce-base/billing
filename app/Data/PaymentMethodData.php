<?php

namespace Modules\Billing\Data;

use Spatie\LaravelData\Data;

class PaymentMethodData extends Data
{
    public function __construct(
        public string $providerPaymentMethodId,
        public string $type,
        public ?string $cardBrand = null,
        public ?string $cardLastFour = null,
        public ?int $cardExpMonth = null,
        public ?int $cardExpYear = null,
    ) {}
}
