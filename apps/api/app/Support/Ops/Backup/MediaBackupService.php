<?php

namespace App\Support\Ops\Backup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

final class MediaBackupService
{
    public function __construct(
        private readonly BackupPaths $paths,
    ) {}

    /**
     * @return array{path: string, bytes: int}
     */
    public function run(?string $timestamp = null): array
    {
        app(BackupDependencyChecker::class)->assertReady(
            requireMysqldump: ! app()->environment('testing')
                && (string) config('backup.database.driver', 'mysqldump') !== 'fake'
        );

        $this->paths->ensureLayout();
        $target = $this->paths->dailyMediaPath($timestamp);
        $tmp = $target.'.partial';
        @unlink($tmp);
        @unlink($target);

        $storageRoot = storage_path();
        $relativePaths = config('backup.media.paths', ['app/public', 'app/private']);
        $existing = [];

        foreach ($relativePaths as $relative) {
            $absolute = $storageRoot.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $relative);
            if (! is_dir($absolute)) {
                File::makeDirectory($absolute, 0755, true);
            }
            $existing[] = (string) $relative;
        }

        if ($existing === []) {
            throw new RuntimeException('No media paths configured for backup.');
        }

        $timeout = max(30, (int) config('backup.media.timeout_seconds', 600));

        $command = array_merge(
            ['tar', '-czf', $tmp, '-C', $storageRoot],
            $existing,
        );

        $result = Process::timeout($timeout)->run($command);
        if ($result->failed()) {
            @unlink($tmp);
            Log::error('ops.backup.media_failed', [
                'exit' => $result->exitCode(),
                'error' => 'tar failed',
            ]);
            throw new RuntimeException('Media tar backup failed with exit code '.$result->exitCode());
        }

        if (! is_file($tmp) || filesize($tmp) < 1) {
            @unlink($tmp);
            throw new RuntimeException('Media backup produced an empty archive.');
        }

        rename($tmp, $target);
        @chmod($target, 0600);

        $bytes = (int) filesize($target);
        Log::info('ops.backup.media_ok', [
            'file' => basename($target),
            'bytes' => $bytes,
        ]);

        return ['path' => $target, 'bytes' => $bytes];
    }
}
