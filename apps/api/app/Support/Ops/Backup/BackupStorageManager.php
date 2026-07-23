<?php

namespace App\Support\Ops\Backup;

use RuntimeException;

final class BackupStorageManager
{
    public function driver(?string $name = null): BackupStorage
    {
        $name = strtolower($name ?? (string) config('backup.destination.driver', 'local'));

        return match ($name) {
            'local' => app(LocalBackupStorage::class),
            's3', 's3_compatible' => app(S3CompatibleBackupStorage::class),
            default => throw new RuntimeException("Unknown backup destination driver [{$name}]."),
        };
    }

    /**
     * Replicate local backup artifacts to the configured destination.
     *
     * @param  list<string>  $localPaths
     * @return list<string> object keys attempted
     */
    public function replicateAll(array $localPaths): array
    {
        $storage = $this->driver();
        $keys = [];

        foreach ($localPaths as $path) {
            if (! is_file($path)) {
                continue;
            }

            $basename = basename($path);
            $tier = 'daily';
            foreach (['daily', 'weekly', 'monthly'] as $candidate) {
                if (str_contains($path, DIRECTORY_SEPARATOR.$candidate.DIRECTORY_SEPARATOR)
                    || str_contains($path, '/'.$candidate.'/')) {
                    $tier = $candidate;
                    break;
                }
            }

            $objectKey = $tier.'/'.$basename;
            $storage->put($path, $objectKey);
            $keys[] = $objectKey;
        }

        return $keys;
    }
}
