<?php

namespace App\Console\Commands;

use App\Support\Ops\Backup\BackupDependencyGate;
use App\Support\Ops\Backup\BackupPaths;
use App\Support\Ops\Backup\BackupVerifier;
use App\Support\Ops\Backup\MediaBackupService;
use Illuminate\Console\Command;
use Throwable;

class OpsBackupMediaCommand extends Command
{
    protected $signature = 'ops:backup-media
        {--skip-verify : Skip post-backup verification}
        {--dry-run : Validate dependencies and show target path only}';

    protected $description = 'Create a compressed archive of storage media (app/public + app/private).';

    public function handle(
        MediaBackupService $media,
        BackupVerifier $verifier,
        BackupPaths $paths,
        BackupDependencyGate $deps,
    ): int {
        if (! config('backup.enabled')) {
            $this->warn('Backups disabled (BACKUP_ENABLED=false).');

            return self::SUCCESS;
        }

        try {
            $check = $deps->check(requireMysqldump: false);
            if (! $check['ok']) {
                $this->error('Backup dependencies not ready.');
                foreach ($check['messages'] as $message) {
                    $this->warn($message);
                }

                return self::FAILURE;
            }

            $paths->ensureLayout();
            $target = $paths->dailyMediaPath();

            if ($this->option('dry-run')) {
                $this->line('Would write media backup: '.basename($target));
                $this->info('Dry run complete; no file written.');

                return self::SUCCESS;
            }

            $result = $media->run();
            if (! $this->option('skip-verify') && (bool) config('backup.verification.enabled', true)) {
                $verifier->assertOk($result['path'], 'media');
            }

            $this->info('Media backup written: '.basename($result['path']).' ('.$result['bytes'].' bytes)');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Media backup failed.');
            report($e);

            return self::FAILURE;
        }
    }
}
