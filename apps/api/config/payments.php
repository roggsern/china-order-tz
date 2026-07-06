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
    | Future Gateway Configuration
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

];
