<?php

namespace Modules\Billing\Services\Gateways;

use Illuminate\Support\Facades\Log;

/**
 * Base gateway class with common functionality.
 *
 * This class provides shared error handling and logging
 * that all payment gateways can use.
 */
abstract class BaseGateway
{
    /**
     * Handle API errors in a consistent way.
     */
    protected function handleApiError(\Exception $e, array $context = []): never
    {
        Log::error('Payment gateway error', [
            'gateway' => $this->getGatewayName(),
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'context' => $context,
        ]);

        throw $e;
    }

    /**
     * Log gateway operations for debugging.
     */
    protected function logOperation(string $operation, array $data): void
    {
        if (! config('billing.logging.enabled', true)) {
            return;
        }

        Log::channel(config('billing.logging.channel', 'stack'))->info(
            "Payment gateway operation: {$operation}",
            [
                'gateway' => $this->getGatewayName(),
                'operation' => $operation,
                'data' => $data,
            ]
        );
    }

    /**
     * Get the name of the current gateway.
     */
    protected function getGatewayName(): string
    {
        $class = get_class($this);
        $parts = explode('\\', $class);
        $className = end($parts);

        return str_replace('Gateway', '', $className);
    }
}
