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
        'order_cancelled' => ['in_app', 'push'],
        'payment_confirmed' => ['in_app', 'whatsapp', 'email', 'push'],
        'warehouse_picking_started' => ['in_app'],
        'warehouse_pick_assigned' => ['in_app'],
        'warehouse_pick_completed' => ['in_app'],
        'warehouse_packed' => ['in_app'],
        'warehouse_ready_to_ship' => ['in_app'],
        'warehouse_ready_for_pickup' => ['in_app'],
        'warehouse_ready_for_delivery_arrangement' => ['in_app'],
        'shipment_created' => ['in_app', 'push'],
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
        // meta_cloud | dialog360 | twilio_whatsapp | ultramsg | greenapi
        'driver' => env('WHATSAPP_PROVIDER', env('NOTIFICATION_WHATSAPP_DRIVER', 'meta_cloud')),
        'configured' => (bool) env('NOTIFICATION_WHATSAPP_CONFIGURED', false),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'default_language' => env('WHATSAPP_DEFAULT_LANGUAGE', 'en'),
        'timeout' => (int) env('WHATSAPP_HTTP_TIMEOUT', 10),
        'connect_timeout' => (int) env('WHATSAPP_HTTP_CONNECT_TIMEOUT', 5),

        /*
        | Meta-approved template names + ordered body parameter keys from notification.data.
        | These are NOT the DB notification_templates bodies used for in-app/email.
        */
        'templates' => [
            'order_created' => [
                'name' => env('WHATSAPP_TEMPLATE_ORDER_CREATED', 'order_created'),
                'language' => env('WHATSAPP_TEMPLATE_ORDER_CREATED_LANG'),
                'body_params' => ['customer_name', 'order_number', 'order_total', 'currency'],
            ],
            'payment_confirmed' => [
                'name' => env('WHATSAPP_TEMPLATE_PAYMENT_CONFIRMED', 'payment_confirmed'),
                'language' => env('WHATSAPP_TEMPLATE_PAYMENT_CONFIRMED_LANG'),
                'body_params' => ['customer_name', 'order_number', 'order_total', 'currency'],
            ],
            'shipment_arrived_tanzania' => [
                'name' => env('WHATSAPP_TEMPLATE_SHIPMENT_ARRIVED_TANZANIA', 'shipment_arrived_tanzania'),
                'language' => env('WHATSAPP_TEMPLATE_SHIPMENT_ARRIVED_TANZANIA_LANG'),
                'body_params' => ['customer_name', 'order_number', 'location'],
            ],
            'order_delivered' => [
                'name' => env('WHATSAPP_TEMPLATE_ORDER_DELIVERED', 'order_delivered'),
                'language' => env('WHATSAPP_TEMPLATE_ORDER_DELIVERED_LANG'),
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
