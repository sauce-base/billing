<?php

namespace Modules\Billing\Services;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Modules\Billing\Contracts\PaymentGatewayInterface;
use Modules\Billing\Services\Gateways\StripeGateway;

class PaymentGatewayFactory
{
    public function __construct(
        private Container $app,
    ) {}

    public function driver(string $name): PaymentGatewayInterface
    {
        return match ($name) {
            'stripe' => $this->app->make(StripeGateway::class),
            default => throw new InvalidArgumentException("Unsupported payment gateway driver: {$name}"),
        };
    }
}
