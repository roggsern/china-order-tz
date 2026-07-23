<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduler health (RC1-G4C1)
    |--------------------------------------------------------------------------
    |
    | Heartbeat key: ops:scheduler:heartbeat (written every minute by schedule).
    | Runtime start: ops:runtime:started_at (Cache::add on boot via ops:runtime-heartbeat).
    |
    */

    'bypass_scheduler_health' => (bool) env('OPS_BYPASS_SCHEDULER_HEALTH', false),

    'scheduler_heartbeat_max_age_minutes' => (int) env('OPS_SCHEDULER_HEARTBEAT_MAX_AGE_MINUTES', 10),

    'scheduler_startup_grace_minutes' => (int) env('OPS_SCHEDULER_STARTUP_GRACE_MINUTES', 5),

];
