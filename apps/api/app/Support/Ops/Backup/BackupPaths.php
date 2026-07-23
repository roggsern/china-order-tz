<?php

namespace App\Support\Ops\Backup;

use Illuminate\Support\Facades\File;
use RuntimeException;

final class BackupPaths
{
    public function root(): string
    {
        $root = rtrim((string) config('backup.root'), DIRECTORY_SEPARATOR);
        if ($root === '') {
            throw new RuntimeException('backup.root is not configured.');
        }

        return $root;
    }

    public function tier(string $tier): string
    {
        $tier = strtolower($tier);
        if (! in_array($tier, ['daily', 'weekly', 'monthly'], true)) {
            throw new RuntimeException("Invalid backup tier [{$tier}].");
        }

        return $this->root().DIRECTORY_SEPARATOR.$tier;
    }

    /**
     * @return list<string>
     */
    public function ensureLayout(): array
    {
        $created = [];
        foreach (['daily', 'weekly', 'monthly'] as $tier) {
            $path = $this->tier($tier);
            if (! is_dir($path)) {
                File::makeDirectory($path, 0750, true);
                $created[] = $path;
            }
        }

        // Deny web exposure if root were ever under public/ (defense in depth).
        $htaccess = $this->root().DIRECTORY_SEPARATOR.'.htaccess';
        if (! is_file($htaccess)) {
            File::put($htaccess, "Require all denied\nDeny from all\n");
            @chmod($htaccess, 0640);
        }

        $gitignore = $this->root().DIRECTORY_SEPARATOR.'.gitignore';
        if (! is_file($gitignore)) {
            File::put($gitignore, "*\n!.gitignore\n!.htaccess\n");
        }

        return $created;
    }

    public function timestamp(): string
    {
        return now()->format('Y-m-d_His');
    }

    public function dailyDatabasePath(?string $timestamp = null): string
    {
        $timestamp ??= $this->timestamp();

        return $this->tier('daily').DIRECTORY_SEPARATOR.$timestamp.'-'.config('backup.database.filename_suffix');
    }

    public function dailyMediaPath(?string $timestamp = null): string
    {
        $timestamp ??= $this->timestamp();

        return $this->tier('daily').DIRECTORY_SEPARATOR.$timestamp.'-'.config('backup.media.filename_suffix');
    }
}
