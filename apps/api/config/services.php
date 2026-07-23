<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

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
        'http' => [
            'timeout' => env('NMB_HTTP_TIMEOUT', 30),
            'connect_timeout' => env('NMB_HTTP_CONNECT_TIMEOUT', 10),
            'retry_times' => env('NMB_HTTP_RETRY_TIMES', 2),
        ],
        'webhook' => [
            /*
             * Production always forces verification (see NmbWebhookSignatureVerifier::isRequired).
             * Outside production, false allows local/sandbox unsigned callbacks explicitly.
             */
            'require_signature' => env('NMB_WEBHOOK_REQUIRE_SIGNATURE', false),
            'secret' => env('NMB_WEBHOOK_SECRET'),
            /*
             * notification_secret — MPGS X-Notification-Secret (default, evidenced)
             * hmac_sha256 — HMAC-SHA256(raw body) vs X-Notification-Signature / X-Signature
             * both — require both checks
             */
            'scheme' => env('NMB_WEBHOOK_SCHEME', 'notification_secret'),
            'replay_ttl_seconds' => env('NMB_WEBHOOK_REPLAY_TTL_SECONDS', 86400),
        ],
        'logging' => [
            'channel' => env('NMB_LOG_CHANNEL', 'stack'),
        ],
    ],

];
