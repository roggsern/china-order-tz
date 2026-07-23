<?php

namespace App\Support\Ops\Backup;

use RuntimeException;

final class LocalBackupStorage implements BackupStorage
{
    public function __construct(
        private readonly BackupPaths $paths,
    ) {}

    public function driverName(): string
    {
        return 'local';
    }

    public function validateConfig(): void
    {
        $root = rtrim($this->paths->root(), DIRECTORY_SEPARATOR);
        if ($root === '') {
            throw new RuntimeException('Backup root is not configured.');
        }
    }

    public function put(string $localAbsolutePath, string $objectKey): void
    {
        unset($objectKey);

        if (! is_file($localAbsolutePath)) {
            throw new RuntimeException('Local backup file missing.');
        }

        // Local driver: artifact already resides on BACKUP_ROOT volume.
    }
}
