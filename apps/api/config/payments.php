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
        'environment' => env('NMB_ENVIRONMENT', 'sandbox'),
        'base_url' => env('NMB_BASE_URL'),
        'api_version' => env('NMB_API_VERSION', '85'),
        'merchant_id' => env('NMB_MERCHANT_ID'),
        'username' => env('NMB_USERNAME'),
        'password' => env('NMB_PASSWORD'),
        'return_url' => env('NMB_RETURN_URL'),
        'callback_url' => env('NMB_CALLBACK_URL'),
        'merchant_name' => env('NMB_MERCHANT_NAME'),
        'merchant_url' => env('NMB_MERCHANT_URL'),
        'auto_verify_after_callback' => env('NMB_AUTO_VERIFY_AFTER_CALLBACK', true),
        'auto_complete_after_verification' => env('NMB_AUTO_COMPLETE_AFTER_VERIFICATION', true),
        'process_callbacks_async' => env('NMB_PROCESS_CALLBACKS_ASYNC', true),
        'http_timeout' => (int) env('NMB_HTTP_TIMEOUT', 30),
        'http_connect_timeout' => (int) env('NMB_HTTP_CONNECT_TIMEOUT', 10),
        'http_retry_times' => (int) env('NMB_HTTP_RETRY_TIMES', 2),
        'webhook_require_signature' => env('NMB_WEBHOOK_REQUIRE_SIGNATURE', false),
        'webhook_scheme' => env('NMB_WEBHOOK_SCHEME', 'notification_secret'),
        'webhook_replay_ttl_seconds' => (int) env('NMB_WEBHOOK_REPLAY_TTL_SECONDS', 86400),
        'log_channel' => env('NMB_LOG_CHANNEL', 'stack'),
    ],

    'vodacom' => [
        'enabled' => env('PAYMENT_VODACOM_ENABLED', false),
    ],

    'airtel' => [
        'enabled' => env('PAYMENT_AIRTEL_ENABLED', false),
    ],

    'reference_prefix' => env('PAYMENT_REFERENCE_PREFIX', 'PAY'),
    'reference_sequence_padding' => (int) env('PAYMENT_REFERENCE_SEQUENCE_PADDING', 6),

    /*
    |--------------------------------------------------------------------------
    | Payment Orchestrator
    |--------------------------------------------------------------------------
    |
    | Provider-agnostic transaction layer. Default provider is NMB; additional
    | adapters (Selcom, Stripe, M-Pesa, etc.) register via DI.
    |
    */

    'orchestrator' => [
        'default_provider' => env('PAYMENT_ORCHESTRATOR_DEFAULT_PROVIDER', 'nmb'),
        'merchant_reference_prefix' => env('PAYMENT_ORCHESTRATOR_MERCHANT_PREFIX', 'COTZ-PAY'),
        'merchant_reference_padding' => (int) env('PAYMENT_ORCHESTRATOR_MERCHANT_PADDING', 6),
    ],

];
