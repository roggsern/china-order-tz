<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RC1-G4C3 — Monitoring & alerting foundation
    |--------------------------------------------------------------------------
    */

    'enabled' => (bool) env('MONITORING_ENABLED', true),

    'environment' => env('MONITORING_ENVIRONMENT', env('APP_ENV', 'production')),

    'release' => env('MONITORING_RELEASE', env('APP_VERSION', 'dev')),

    'error' => [
        // log | sentry
        'driver' => env('ERROR_MONITORING_DRIVER', 'log'),
        'sentry_dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
    ],

    'alerts' => [
        // log | slack
        'driver' => env('ALERT_DRIVER', 'log'),
        'slack_webhook_url' => env('ALERT_SLACK_WEBHOOK_URL'),
        'slack_channel' => env('ALERT_SLACK_CHANNEL'),
    ],

    'queue' => [
        'failed_jobs_warning' => (int) env('MONITORING_FAILED_JOBS_WARNING', 10),
        'pending_jobs_warning' => (int) env('MONITORING_PENDING_JOBS_WARNING', 100),
        // Fresh heartbeat must be newer than this TTL (default 300s).
        'worker_heartbeat_ttl_seconds' => (int) env('QUEUE_WORKER_HEARTBEAT_TTL_SECONDS', 300),
        // Missing heartbeat is tolerated only during startup grace.
        'worker_startup_grace_seconds' => (int) env('QUEUE_WORKER_STARTUP_GRACE_SECONDS', 120),
        // PHPUnit sets true via env; production must leave false.
        'bypass_worker_health' => (bool) env('MONITORING_BYPASS_QUEUE_WORKER_HEALTH', false),
        // Legacy alias (minutes) — unused when TTL seconds is set; kept for docs compatibility.
        'worker_heartbeat_max_age_minutes' => (int) env('MONITORING_WORKER_HEARTBEAT_MAX_AGE', 5),
    ],

    'payments' => [
        'stuck_pending_minutes' => (int) env('MONITORING_PAYMENT_STUCK_MINUTES', 60),
        'stuck_pending_warning_count' => (int) env('MONITORING_PAYMENT_STUCK_COUNT', 5),
    ],

    'disk' => [
        // Percent used (0-100) above which disk check fails (soft degrade).
        'warning_percent' => (int) env('MONITORING_DISK_WARNING_PERCENT', 90),
        'path' => env('MONITORING_DISK_PATH'), // default: storage_path()
    ],

    'health' => [
        // When true in production, omit per-check map from JSON (uptime-friendly).
        // Default false preserves G4C1/G4C2 public boolean checks contract.
        'critical_only' => (bool) env('HEALTH_CRITICAL_ONLY', false),
    ],

];
