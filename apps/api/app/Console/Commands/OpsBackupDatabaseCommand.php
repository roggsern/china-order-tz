<?php

namespace App\Console\Commands;

use App\Support\Ops\Backup\BackupDependencyGate;
use App\Support\Ops\Backup\BackupPaths;
use App\Support\Ops\Backup\BackupVerifier;
use App\Support\Ops\Backup\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class OpsBackupDatabaseCommand extends Command
{
    protected $signature = 'ops:backup-database
        {--skip-verify : Skip post-backup verification}
        {--dry-run : Validate dependencies and show target path only}';

    protected $description = 'Create a compressed, timestamped MySQL database backup.';

    public function handle(
        DatabaseBackupService $database,
        BackupVerifier $verifier,
        BackupPaths $paths,
        BackupDependencyGate $deps,
    ): int {
        if (! config('backup.enabled')) {
            $this->warn('Backups disabled (BACKUP_ENABLED=false).');

            return self::SUCCESS;
        }

        try {
            $requireMysqldump = ! app()->environment('testing')
                && (string) config('backup.database.driver', 'mysqldump') !== 'fake';

            $check = $deps->check(requireMysqldump: $requireMysqldump);
            if (! $check['ok']) {
                $this->error('Backup dependencies not ready.');
                foreach ($check['messages'] as $message) {
                    $this->warn($message);
                }

                return self::FAILURE;
            }

            $paths->ensureLayout();
            $target = $paths->dailyDatabasePath();

            if ($this->option('dry-run')) {
                $this->line('Would write database backup: '.basename($target));
                $this->info('Dry run complete; no file written.');

                return self::SUCCESS;
            }

            $result = $database->run();
            if (! $this->option('skip-verify') && (bool) config('backup.verification.enabled', true)) {
                $verifier->assertOk($result['path'], 'database');
            }

            $this->info('Database backup written: '.basename($result['path']).' ('.$result['bytes'].' bytes)');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Database backup failed.');
            report($e);

            return self::FAILURE;
        }
    }
}
