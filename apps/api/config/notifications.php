<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default channels per business event
    |--------------------------------------------------------------------------
    | Business modules publish events only. The platform selects channels.
    | Only in_app delivers in this phase; other channels log "Not Configured".
    */
    'event_channels' => [
        'order_created' => ['in_app'],
        'payment_confirmed' => ['in_app'],
        'warehouse_picking_started' => ['in_app'],
        'warehouse_packed' => ['in_app'],
        'warehouse_ready_to_ship' => ['in_app'],
        'shipment_created' => ['in_app'],
        'tracking_updated' => ['in_app'],
        'shipment_status_updated' => ['in_app'],
        'order_delivered' => ['in_app'],
        'password_reset' => ['in_app', 'email'],
        'otp_requested' => ['in_app', 'sms'],
        'return_requested' => ['in_app'],
        'return_approved' => ['in_app'],
        'return_rejected' => ['in_app'],
        'refund_completed' => ['in_app'],
        'purchase_order_confirmed' => ['in_app'],
        'goods_received' => ['in_app'],
        'low_margin_alert' => ['in_app'],
        'cost_increase_alert' => ['in_app'],
        'agent_pickup_ready' => ['in_app'],
        'agent_pickup_authorized' => ['in_app'],
        'agent_pickup_authorization_revoked' => ['in_app'],
        'agent_pickup_scheduled' => ['in_app'],
        'agent_warehouse_released' => ['in_app'],
        'agent_handover_completed' => ['in_app'],
    ],

    'email' => [
        // smtp | mailgun | sendgrid | ses
        'driver' => env('NOTIFICATION_EMAIL_DRIVER', 'smtp'),
        'configured' => (bool) env('NOTIFICATION_EMAIL_CONFIGURED', false),
    ],

    'whatsapp' => [
        // meta_cloud | dialog360 | twilio_whatsapp | ultramsg | greenapi
        'driver' => env('NOTIFICATION_WHATSAPP_DRIVER', 'meta_cloud'),
        'configured' => (bool) env('NOTIFICATION_WHATSAPP_CONFIGURED', false),
    ],

    'sms' => [
        // twilio | africas_talking | beem | local_gateway
        'driver' => env('NOTIFICATION_SMS_DRIVER', 'twilio'),
        'configured' => (bool) env('NOTIFICATION_SMS_CONFIGURED', false),
    ],

    'push' => [
        // firebase | onesignal | expo
        'driver' => env('NOTIFICATION_PUSH_DRIVER', 'firebase'),
        'configured' => (bool) env('NOTIFICATION_PUSH_CONFIGURED', false),
    ],

];
