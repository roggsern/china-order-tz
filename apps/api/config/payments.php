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
