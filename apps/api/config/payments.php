<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Used when no method-specific gateway applies during development.
    | Supported values: mock, nmb
    |
    */

    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Gateway Configuration
    |--------------------------------------------------------------------------
    */

    'nmb' => [
        'enabled' => env('PAYMENT_NMB_ENABLED', false),
        'base_url' => env('NMB_BASE_URL'),
        'client_id' => env('NMB_CLIENT_ID'),
        'client_secret' => env('NMB_CLIENT_SECRET'),
        'merchant_id' => env('NMB_MERCHANT_ID'),
        'callback_url' => env('NMB_CALLBACK_URL'),
        'test_mode' => env('NMB_TEST_MODE', true),
        'mock_checkout_url' => env('NMB_MOCK_CHECKOUT_URL', 'https://sandbox.nmb.co.tz/pay/mock'),
    ],

    'vodacom' => [
        'enabled' => env('PAYMENT_VODACOM_ENABLED', false),
    ],

    'airtel' => [
        'enabled' => env('PAYMENT_AIRTEL_ENABLED', false),
    ],

    'reference_prefix' => env('PAYMENT_REFERENCE_PREFIX', 'PAY'),
    'reference_sequence_padding' => (int) env('PAYMENT_REFERENCE_SEQUENCE_PADDING', 6),

];
