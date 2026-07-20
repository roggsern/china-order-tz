<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Profit protection
    |--------------------------------------------------------------------------
    | Before applying a discount, estimate margin using projected revenue minus
    | estimated line costs (supplier cost history / product cost_price).
    */
    'low_margin_threshold' => (float) env('PROMOTION_LOW_MARGIN_THRESHOLD', 10),

    /** When true, reject promotions that would drop estimated margin below threshold. */
    'reject_low_margin' => (bool) env('PROMOTION_REJECT_LOW_MARGIN', true),

    /** Allow admin override when reject_low_margin is true (admin apply/create only). */
    'allow_admin_override' => (bool) env('PROMOTION_ALLOW_ADMIN_OVERRIDE', true),

    /** Max automatic promotions stacked on a single checkout (1 = exclusive). */
    'max_automatic_stack' => (int) env('PROMOTION_MAX_AUTOMATIC_STACK', 1),
];
