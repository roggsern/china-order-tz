<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RC1-G4C2 — Backup & DR foundation
    |--------------------------------------------------------------------------
    |
    | Backups MUST live on a persistent volume / host path — never only in the
    | container writable layer. Production Compose mounts `app_backups` at the
    | default root below. Nginx does not mount this path.
    |
    */

    'enabled' => (bool) env('BACKUP_ENABLED', true),

    'root' => env('BACKUP_ROOT', storage_path('backups')),

    /*
    | Directory layout under root:
    |   daily/   YYYY-MM-DD_HHMMSS-database.sql.gz
    |   daily/   YYYY-MM-DD_HHMMSS-media.tar.gz
    |   weekly/  (promoted copies)
    |   monthly/ (promoted copies)
    */

    'retention' => [
        'daily' => (int) env('BACKUP_RETENTION_DAILY', 7),
        'weekly' => (int) env('BACKUP_RETENTION_WEEKLY', 4),
        'monthly' => (int) env('BACKUP_RETENTION_MONTHLY', 6),
    ],

    'database' => [
        'filename_suffix' => 'database.sql.gz',
        'min_bytes' => (int) env('BACKUP_DB_MIN_BYTES', 64),
        // Use mysqldump when available; "fake" writes a valid-looking gzip for tests.
        'driver' => env('BACKUP_DB_DRIVER', 'mysqldump'),
        'mysqldump_bin' => env('BACKUP_MYSQLDUMP_BIN', 'mysqldump'),
        'mysql_bin' => env('BACKUP_MYSQL_BIN', 'mysql'),
        // DISABLED is safe for internal Docker network when require_secure_transport=OFF.
        'ssl_mode' => env('BACKUP_DB_SSL_MODE', 'DISABLED'),
        'ssl_ca' => env('BACKUP_DB_SSL_CA'),
        'timeout_seconds' => (int) env('BACKUP_DB_TIMEOUT', 600),
        'connectivity_check' => (bool) env('BACKUP_DB_CONNECTIVITY_CHECK', true),
    ],

    'media' => [
        'filename_suffix' => 'media.tar.gz',
        'min_bytes' => (int) env('BACKUP_MEDIA_MIN_BYTES', 32),
        // Relative to storage_path(); public uploads + private uploads.
        'paths' => [
            'app/public',
            'app/private',
        ],
        'timeout_seconds' => (int) env('BACKUP_MEDIA_TIMEOUT', 600),
    ],

    'verification' => [
        'enabled' => (bool) env('BACKUP_VERIFY_ENABLED', true),
        'require_gzip_magic' => (bool) env('BACKUP_VERIFY_GZIP_MAGIC', true),
        'require_tar_magic' => (bool) env('BACKUP_VERIFY_TAR_MAGIC', true),
    ],

    /*
    | Encryption readiness: set BACKUP_ENCRYPT=true and provide a gpg recipient
    | later. Foundation stores chmod 0600 archives; encryption is optional.
    */
    'encrypt' => (bool) env('BACKUP_ENCRYPT', false),
    'encrypt_recipient' => env('BACKUP_ENCRYPT_RECIPIENT'),

    'schedule' => [
        'daily_at' => env('BACKUP_DAILY_AT', '02:15'),
    ],

    'destination' => [
        // local | s3 | s3_compatible
        'driver' => env('BACKUP_DRIVER', 'local'),
        's3' => [
            'key' => env('BACKUP_S3_KEY', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('BACKUP_S3_SECRET', env('AWS_SECRET_ACCESS_KEY')),
            'bucket' => env('BACKUP_S3_BUCKET', env('AWS_BUCKET')),
            'region' => env('BACKUP_S3_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
            'endpoint' => env('BACKUP_S3_ENDPOINT', env('AWS_ENDPOINT')),
            'path_prefix' => env('BACKUP_S3_PATH_PREFIX', 'china-order-tz'),
            'use_path_style' => (bool) env('BACKUP_S3_USE_PATH_STYLE', true),
        ],
    ],

];
