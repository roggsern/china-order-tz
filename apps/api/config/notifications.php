<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default channels per business event
    |--------------------------------------------------------------------------
    | Business modules publish events only. The platform selects channels.
    | Managed admin settings events (order.created / order.paid / shipment.delivered)
    | also apply enablement + provider availability filtering.
    */
    'event_channels' => [
        // Wave 6C — Tier A launch push set (append-only; preserve email/whatsapp).
        'order_created' => ['in_app', 'whatsapp', 'email', 'push'],
        'order_cancelled' => ['in_app', 'whatsapp', 'push'],
        'payment_confirmed' => ['in_app', 'whatsapp', 'email', 'push'],
        'order_processing' => ['in_app', 'whatsapp', 'push'],
        'warehouse_picking_started' => ['in_app'],
        'warehouse_pick_assigned' => ['in_app'],
        'warehouse_pick_completed' => ['in_app'],
        'warehouse_packed' => ['in_app'],
        'warehouse_ready_to_ship' => ['in_app'],
        'warehouse_ready_for_pickup' => ['in_app', 'whatsapp'],
        'warehouse_ready_for_delivery_arrangement' => ['in_app'],
        'shipment_created' => ['in_app', 'whatsapp', 'push'],
        'shipment_arrived_tanzania' => ['in_app', 'whatsapp', 'email', 'push'],
        'company_handover_pickup_requested' => ['in_app'],
        'company_handover_delivery_requested' => ['in_app'],
        'company_handover_completed_pickup' => ['in_app'],
        'company_handover_completed_delivery' => ['in_app'],
        'tracking_updated' => ['in_app'],
        'shipment_status_updated' => ['in_app'],
        'order_delivered' => ['in_app', 'whatsapp', 'email', 'push'],
        'local_order_completed_pickup' => ['in_app'],
        'local_order_completed_delivery_arrangement' => ['in_app'],
        'password_reset' => ['in_app', 'email'],
        'password_changed' => ['in_app', 'email', 'push'],
        'email_change_requested' => ['in_app', 'email'],
        'email_changed' => ['in_app', 'email', 'push'],
        'email_verification_requested' => ['in_app', 'email'],
        'email_verified' => ['in_app', 'email'],
        'otp_requested' => ['in_app', 'sms'],
        'return_requested' => ['in_app'],
        'return_approved' => ['in_app'],
        'return_rejected' => ['in_app'],
        'refund_started' => ['in_app'],
        'refund_requested' => ['in_app'],
        'refund_approved' => ['in_app'],
        'refund_completed' => ['in_app'],
        'refund_failed' => ['in_app'],
        'refund_rejected' => ['in_app'],
        'purchase_order_confirmed' => ['in_app'],
        'purchase_requirement_ready' => ['in_app'],
        'china_purchase_completed' => ['in_app'],
        'goods_received' => ['in_app'],
        'low_margin_alert' => ['in_app'],
        'cost_increase_alert' => ['in_app'],
        'agent_pickup_ready' => ['in_app'],
        'agent_pickup_authorized' => ['in_app'],
        'agent_pickup_authorization_revoked' => ['in_app'],
        'agent_pickup_scheduled' => ['in_app'],
        'agent_warehouse_released' => ['in_app'],
        'agent_handover_completed' => ['in_app'],
        'review_approved' => ['in_app'],
        'review_rejected' => ['in_app'],
        // Support events previously relied on config fallback ['in_app'] only.
        'support_ticket_created' => ['in_app'],
        // Customer assign notice stays in_app by default; admin assignee gets push explicitly.
        'support_ticket_assigned' => ['in_app'],
        'support_reply_received' => ['in_app', 'push'],
        'support_ticket_resolved' => ['in_app'],
    ],

    'email' => [
        // smtp | mailgun | sendgrid | ses
        'driver' => env('NOTIFICATION_EMAIL_DRIVER', 'smtp'),
        'configured' => (bool) env('NOTIFICATION_EMAIL_CONFIGURED', false),
    ],

    'whatsapp' => [
        // Wave 1 production sender is Ghala only. Other driver names stay unconfigured.
        'driver' => env('WHATSAPP_PROVIDER', env('NOTIFICATION_WHATSAPP_DRIVER', 'ghala')),
        'configured' => (bool) env('GHALA_ENABLED', env('NOTIFICATION_WHATSAPP_CONFIGURED', false)),
        'base_url' => env('GHALA_BASE_URL', 'https://v2.ghala.io'),
        'access_token' => env('GHALA_ACCESS_TOKEN'),
        'webhook_secret' => env('GHALA_WEBHOOK_SECRET'),
        'webhook_replay_ttl_seconds' => (int) env('GHALA_WEBHOOK_REPLAY_TTL_SECONDS', 86400),
        'default_language' => env('GHALA_TEMPLATE_LANGUAGE', env('WHATSAPP_DEFAULT_LANGUAGE', 'en_US')),
        'timeout' => (int) env('GHALA_HTTP_TIMEOUT', env('WHATSAPP_HTTP_TIMEOUT', 10)),
        'connect_timeout' => (int) env('GHALA_HTTP_CONNECT_TIMEOUT', env('WHATSAPP_HTTP_CONNECT_TIMEOUT', 5)),
        'retry_attempts' => (int) env('GHALA_RETRY_ATTEMPTS', 3),
        'retry_sleep_ms' => (int) env('GHALA_RETRY_SLEEP_MS', 200),

        /*
        | Approved Utility template names + ordered body parameter keys from notification.data.
        | These are NOT the DB notification_templates bodies used for in-app/email/push.
        */
        'templates' => [
            'order_created' => [
                'name' => env('WHATSAPP_TEMPLATE_ORDER_CONFIRMATION', 'order_confirmation'),
                'language' => env('WHATSAPP_TEMPLATE_ORDER_CONFIRMATION_LANG'),
                'body_params' => ['customer_name', 'order_number', 'order_total'],
            ],
            'payment_confirmed' => [
                'name' => env('WHATSAPP_TEMPLATE_PAYMENT_RECEIVED', 'payment_received'),
                'language' => env('WHATSAPP_TEMPLATE_PAYMENT_RECEIVED_LANG'),
                'body_params' => ['customer_name', 'order_total', 'order_number'],
            ],
            'order_processing' => [
                'name' => env('WHATSAPP_TEMPLATE_ORDER_PROCESSING', 'order_processing'),
                'language' => env('WHATSAPP_TEMPLATE_ORDER_PROCESSING_LANG'),
                'body_params' => ['customer_name', 'order_number'],
            ],
            'shipment_arrived_tanzania' => [
                'name' => env('WHATSAPP_TEMPLATE_ORDER_ARRIVED_TANZANIA', 'order_arrived_tanzania'),
                'language' => env('WHATSAPP_TEMPLATE_ORDER_ARRIVED_TANZANIA_LANG'),
                'body_params' => ['customer_name', 'order_number'],
            ],
            'warehouse_ready_for_pickup' => [
                'name' => env('WHATSAPP_TEMPLATE_ORDER_READY_FOR_PICKUP', 'order_ready_for_pickup'),
                'language' => env('WHATSAPP_TEMPLATE_ORDER_READY_FOR_PICKUP_LANG'),
                'body_params' => ['customer_name', 'order_number', 'pickup_location'],
            ],
            'shipment_created' => [
                'name' => env('WHATSAPP_TEMPLATE_ORDER_SHIPPED', 'order_shipped'),
                'language' => env('WHATSAPP_TEMPLATE_ORDER_SHIPPED_LANG'),
                'body_params' => ['customer_name', 'order_number', 'destination'],
            ],
            'order_delivered' => [
                'name' => env('WHATSAPP_TEMPLATE_ORDER_DELIVERED', 'order_delivered'),
                'language' => env('WHATSAPP_TEMPLATE_ORDER_DELIVERED_LANG'),
                'body_params' => ['customer_name', 'order_number'],
            ],
            'order_cancelled' => [
                'name' => env('WHATSAPP_TEMPLATE_ORDER_CANCELLED', 'order_cancelled'),
                'language' => env('WHATSAPP_TEMPLATE_ORDER_CANCELLED_LANG'),
                'body_params' => ['customer_name', 'order_number'],
            ],
        ],
    ],

    'sms' => [
        // twilio | africas_talking | beem | local_gateway
        'driver' => env('NOTIFICATION_SMS_DRIVER', 'twilio'),
        'configured' => (bool) env('NOTIFICATION_SMS_CONFIGURED', false),
    ],

    'push' => [
        // Wave 6B implements Expo only. Other drivers remain unsupported.
        // firebase | onesignal | expo
        'driver' => env('NOTIFICATION_PUSH_DRIVER', 'expo'),
        'configured' => (bool) env('NOTIFICATION_PUSH_CONFIGURED', false),

        /*
        | Expo Push API — https://exp.host/--/api/v2/push/send
        | Access token is optional unless enhanced push security is enabled
        | in the Expo/EAS dashboard (Bearer required after that).
        */
        'expo' => [
            'url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
            'access_token' => env('EXPO_ACCESS_TOKEN'),
            'timeout' => (int) env('EXPO_PUSH_TIMEOUT', 10),
            'connect_timeout' => (int) env('EXPO_PUSH_CONNECT_TIMEOUT', 5),
            // Official Expo batch ceiling is 100 messages per request.
            'batch_size' => (int) env('EXPO_PUSH_BATCH_SIZE', 100),
        ],
    ],

];
