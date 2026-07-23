<?php

namespace App\Support\Ops\Backup;

use RuntimeException;

/**
 * S3-compatible destination foundation. Upload requires aws/aws-sdk-php.
 */
final class S3CompatibleBackupStorage implements BackupStorage
{
    public function driverName(): string
    {
        return 's3';
    }

    public function validateConfig(): void
    {
        $cfg = (array) config('backup.destination.s3', []);
        $bucket = (string) ($cfg['bucket'] ?? '');
        $key = (string) ($cfg['key'] ?? '');
        $secret = (string) ($cfg['secret'] ?? '');

        if ($bucket === '' || $key === '' || $secret === '') {
            throw new RuntimeException('S3 backup destination requires bucket, key, and secret.');
        }
    }

    public function put(string $localAbsolutePath, string $objectKey): void
    {
        if (! is_file($localAbsolutePath)) {
            throw new RuntimeException('Local backup file missing.');
        }

        if (! class_exists(\Aws\S3\S3Client::class)) {
            throw new RuntimeException('AWS SDK is required for S3 backup upload.');
        }

        throw new RuntimeException('S3 backup upload is not enabled in this foundation build.');
    }
}
