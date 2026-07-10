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
    ],

];
