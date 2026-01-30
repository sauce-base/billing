<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Configuration for Stripe payment processing.
    | These credentials should be stored securely in your .env file.
    |
    */
    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Paddle Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Configuration for Paddle payment processing.
    | These credentials should be stored securely in your .env file.
    |
    */
    'paddle' => [
        'vendor_id' => env('PADDLE_VENDOR_ID'),
        'api_key' => env('PADDLE_API_KEY'),
        'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LemonSqueezy Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Configuration for LemonSqueezy payment processing.
    | These credentials should be stored securely in your .env file.
    |
    */
    'lemonsqueezy' => [
        'api_key' => env('LEMONSQUEEZY_API_KEY'),
        'store_id' => env('LEMONSQUEEZY_STORE_ID'),
        'webhook_secret' => env('LEMONSQUEEZY_WEBHOOK_SECRET'),
    ],
];
