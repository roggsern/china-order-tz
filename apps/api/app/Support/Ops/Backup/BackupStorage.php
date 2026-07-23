<?php

namespace App\Support\Ops\Backup;

/**
 * RC1-G4C2 — backup destination abstraction (local / S3-compatible).
 */
interface BackupStorage
{
    public function driverName(): string;

    /**
     * Validate driver configuration without uploading or logging secrets.
     *
     * @throws \RuntimeException
     */
    public function validateConfig(): void;

    /**
     * Replicate a local backup file to the destination.
     * Local driver is a no-op (file already on BACKUP_ROOT volume).
     *
     * @throws \RuntimeException
     */
    public function put(string $localAbsolutePath, string $objectKey): void;
}
