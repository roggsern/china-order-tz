<?php

namespace App\Console\Commands;

use App\Support\Ops\Backup\BackupDependencyChecker;
use Illuminate\Console\Command;
use Throwable;

class OpsBackupCheckCommand extends Command
{
    protected $signature = 'ops:backup-check
        {--json : Output JSON}
        {--require-mysqldump : Always require mysqldump (even when BACKUP_DB_DRIVER=fake)}';

    protected $description = 'Verify backup binaries, writable volumes, and destination configuration.';

    public function handle(BackupDependencyChecker $checker): int
    {
        try {
            $requireMysqldump = $this->option('require-mysqldump')
                || (
                    (string) config('backup.database.driver', 'mysqldump') !== 'fake'
                    && ! app()->environment('testing')
                );

            $result = $checker->check(requireMysqldump: (bool) $requireMysqldump);

            if ($this->option('json')) {
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                foreach ($result['checks'] as $name => $ok) {
                    $label = $ok === null ? 'n/a' : ($ok ? 'ok' : 'fail');
                    $this->line(sprintf('%s: %s', $name, $label));
                }
                foreach ($result['messages'] as $message) {
                    $this->warn($message);
                }
                $this->line('status: '.($result['ok'] ? 'ok' : 'fail'));
            }

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable) {
            $this->error('Backup dependency check failed unexpectedly.');

            return self::FAILURE;
        }
    }
}
