<?php

return [
    'customer_code_prefix' => env('CRM_CUSTOMER_CODE_PREFIX', 'CTZ-CUS'),
    'customer_code_padding' => (int) env('CRM_CUSTOMER_CODE_PADDING', 6),

    /** Days without orders before segmentation may mark dormant (manual override still wins). */
    'dormant_after_days' => (int) env('CRM_DORMANT_AFTER_DAYS', 90),

    /** New customer segment window (computed, not a lifecycle status). */
    'new_customer_days' => (int) env('CRM_NEW_CUSTOMER_DAYS', 30),
];
