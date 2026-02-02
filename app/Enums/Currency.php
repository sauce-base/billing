<?php

namespace Modules\Billing\Enums;

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case BRL = 'BRL';

    /**
     * Get the default currency from configuration.
     */
    public static function default(): self
    {
        return self::from(config('billing.default_currency'));
    }
}
