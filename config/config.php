<?php

return [
    'name' => 'Billing',

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency for billing operations.
    | Uses ISO 4217 currency codes (e.g., EUR, BRL, USD,...).
    |
    */
    'default_currency' => env('BILLING_DEFAULT_CURRENCY', 'EUR'),

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Select which payment gateway to use by default.
    | Available options: 'stripe', 'paddle', 'lemonsqueezy'
    |
    */
    'default_gateway' => env('BILLING_GATEWAY', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Configuration for available payment gateways.
    | Each gateway can be enabled/disabled independently.
    |
    */
    // 'gateways' => [
    //     'stripe' => [
    //         'driver' => \Modules\Billing\Services\Gateways\StripeGateway::class,
    //         'enabled' => true,
    //     ],
    //     'paddle' => [
    //         'driver' => \Modules\Billing\Services\Gateways\PaddleGateway::class,
    //         'enabled' => env('BILLING_PADDLE_ENABLED', false),
    //     ],
    //     'lemonsqueezy' => [
    //         'driver' => \Modules\Billing\Services\Gateways\LemonSqueezyGateway::class,
    //         'enabled' => env('BILLING_LEMONSQUEEZY_ENABLED', false),
    //     ],
    // ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Logging
    |--------------------------------------------------------------------------
    |
    | Enable or disable logging of payment gateway operations.
    | Useful for debugging and monitoring payment flows.
    |
    */
    'logging' => [
        'enabled' => env('BILLING_LOGGING_ENABLED', true),
        'channel' => env('BILLING_LOG_CHANNEL', 'stack'),
    ],
];
