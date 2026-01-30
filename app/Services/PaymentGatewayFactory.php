<?php

namespace Modules\Billing\Services;

use Illuminate\Contracts\Foundation\Application;
use Modules\Billing\Contracts\PaymentGatewayInterface;
use Modules\Billing\Exceptions\PaymentException;

class PaymentGatewayFactory
{
    private array $cachedGateways = [];

    public function __construct(
        protected Application $app
    ) {}

    /**
     * Get payment gateway by name.
     */
    public function driver(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?? config('billing.default_gateway', 'stripe');

        // Check if gateway is enabled in config
        if (! $this->isEnabled($name)) {
            throw PaymentException::gatewayNotEnabled($name);
        }

        // Return cached instance if available
        if (isset($this->cachedGateways[$name])) {
            return $this->cachedGateways[$name];
        }

        // Iterate through tagged gateways to find match
        foreach ($this->app->tagged('payment-gateways') as $gateway) {
            if ($gateway->getName() === $name) {
                $this->cachedGateways[$name] = $gateway;

                return $gateway;
            }
        }

        throw PaymentException::gatewayNotFound($name);
    }

    /**
     * Get all available gateways.
     */
    public function all(): array
    {
        return iterator_to_array($this->app->tagged('payment-gateways'));
    }

    /**
     * Get all enabled gateways.
     */
    public function enabled(): array
    {
        return array_filter($this->all(), fn ($gateway) => $this->isEnabled($gateway->getName())
        );
    }

    /**
     * Check if gateway is enabled in config.
     */
    protected function isEnabled(string $name): bool
    {
        return config("billing.gateways.{$name}.enabled", false);
    }
}
