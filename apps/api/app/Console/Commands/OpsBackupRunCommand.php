<?php

namespace App\Console\Commands;

use App\Support\Ops\Backup\BackupDependencyGate;
use App\Support\Ops\Backup\BackupPaths;
use App\Support\Ops\Backup\BackupStorageManager;
use App\Support\Ops\Backup\BackupVerifier;
use App\Support\Ops\Backup\DatabaseBackupService;
use App\Support\Ops\Backup\MediaBackupService;
use Illuminate\Console\Command;
use Throwable;

class OpsBackupRunCommand extends Command
{
    protected $signature = 'ops:backup-run
        {--skip-database : Skip database backup}
        {--skip-media : Skip media backup}
        {--skip-verify : Skip verification}
        {--skip-replicate : Skip off-site / destination replicate}
        {--dry-run : Show planned backup actions without writing files}';

    protected $description = 'Run database + media backups and verify (no retention deletes).';

    public function handle(
        DatabaseBackupService $database,
        MediaBackupService $media,
        BackupVerifier $verifier,
        BackupPaths $paths,
        BackupDependencyGate $deps,
        BackupStorageManager $destinations,
    ): int {
        if (! config('backup.enabled')) {
            $this->warn('Backups disabled (BACKUP_ENABLED=false).');

            return self::SUCCESS;
        }

        try {
            $requireMysqldump = ! $this->option('skip-database')
                && ! app()->environment('testing')
                && (string) config('backup.database.driver', 'mysqldump') !== 'fake';

            $check = $deps->check(requireMysqldump: $requireMysqldump);
            if (! $check['ok']) {
                $this->error('Backup dependency check failed.');
                foreach ($check['messages'] as $message) {
                    $this->warn($message);
                }

                return self::FAILURE;
            }

            $paths->ensureLayout();
            $timestamp = $paths->timestamp();

            if ($this->option('dry-run')) {
                if (! $this->option('skip-database')) {
                    $this->line('Would write DB: '.basename($paths->dailyDatabasePath($timestamp)));
                }
                if (! $this->option('skip-media')) {
                    $this->line('Would write media: '.basename($paths->dailyMediaPath($timestamp)));
                }
                $this->info('Dry run complete; no files written.');

                return self::SUCCESS;
            }

            $written = [];
            $failed = false;

            if (! $this->option('skip-database')) {
                try {
                    $result = $database->run($timestamp);
                    if (! $this->option('skip-verify') && (bool) config('backup.verification.enabled', true)) {
                        $verifier->assertOk($result['path'], 'database');
                    }
                    $written[] = $result['path'];
                    $this->info('DB: '.basename($result['path']).' ('.$result['bytes'].' B)');
                } catch (Throwable $e) {
                    $failed = true;
                    $this->error('Database backup failed.');
                    report($e);
                }
            }

            if (! $this->option('skip-media')) {
                try {
                    $result = $media->run($timestamp);
                    if (! $this->option('skip-verify') && (bool) config('backup.verification.enabled', true)) {
                        $verifier->assertOk($result['path'], 'media');
                    }
                    $written[] = $result['path'];
                    $this->info('Media: '.basename($result['path']).' ('.$result['bytes'].' B)');
                } catch (Throwable $e) {
                    $failed = true;
                    $this->error('Media backup failed.');
                    report($e);
                }
            }

            if (! $failed && ! $this->option('skip-replicate') && $written !== []) {
                try {
                    $keys = $destinations->replicateAll($written);
                    if ($keys !== []) {
                        $this->line('Replicated: '.count($keys).' object(s) via '.$destinations->driver()->driverName());
                    }
                } catch (Throwable $e) {
                    $failed = true;
                    $this->error('Backup destination replicate failed.');
                    report($e);
                }
            }

            return $failed ? self::FAILURE : self::SUCCESS;
        } catch (Throwable) {
            $this->error('Backup run failed unexpectedly.');

            return self::FAILURE;
        }
    }
}
