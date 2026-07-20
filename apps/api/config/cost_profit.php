<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Exchange rate snapshots (order currency → TZS reporting base)
    |--------------------------------------------------------------------------
    | Historical profits never re-read these after capture on the cost snapshot.
    */
    'exchange_rates' => [
        'TZS' => 1,
        'USD' => (float) env('COST_PROFIT_USD_TZS_RATE', 2600),
        'CNY' => (float) env('COST_PROFIT_CNY_TZS_RATE', 360),
    ],

    'default_exchange_rate' => 1,

    /** Margin % below this triggers admin LowMarginDetected notification. */
    'low_margin_threshold' => (float) env('COST_PROFIT_LOW_MARGIN_THRESHOLD', 10),
];
