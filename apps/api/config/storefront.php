<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storefront visitor session inactivity timeout (minutes)
    |--------------------------------------------------------------------------
    |
    | After this period without identify calls, a new storefront session row is
    | created while preserving the same visitor record.
    |
    */
    'visitor_session_timeout_minutes' => (int) env('STOREFRONT_VISITOR_SESSION_TIMEOUT_MINUTES', 30),
];
