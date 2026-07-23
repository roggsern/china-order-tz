<?php

namespace App\Console\Commands;

use App\Support\Ops\Backup\BackupPaths;
use App\Support\Ops\Backup\BackupVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class OpsBackupVerifyCommand extends Command
{
    protected $signature = 'ops:backup-verify {path? : Specific backup file to verify}
        {--latest : Verify newest daily database + media backups}';

    protected $description = 'Verify backup file existence, size, and basic format integrity.';

    public function handle(BackupVerifier $verifier, BackupPaths $paths): int
    {
        try {
            $targets = [];

            if ($this->argument('path')) {
                $targets[] = (string) $this->argument('path');
            } elseif ($this->option('latest')) {
                $paths->ensureLayout();
                $daily = $paths->tier('daily');
                $targets = array_values(array_filter([
                    $this->newestMatching($daily, 'database.sql.gz'),
                    $this->newestMatching($daily, 'media.tar.gz'),
                ]));
                if ($targets === []) {
                    $this->error('No daily backups found to verify.');

                    return self::FAILURE;
                }
            } else {
                $this->error('Provide {path} or --latest.');

                return self::FAILURE;
            }

            $ok = true;
            foreach ($targets as $target) {
                $result = $verifier->verifyFile($target);
                $label = basename($target);
                if ($result['ok']) {
                    $this->info("OK  {$label}");
                } else {
                    $ok = false;
                    $this->error("FAIL {$label}: ".implode(' ', $result['messages']));
                }
            }

            return $ok ? self::SUCCESS : self::FAILURE;
        } catch (Throwable) {
            $this->error('Backup verification failed unexpectedly.');

            return self::FAILURE;
        }
    }

    private function newestMatching(string $directory, string $suffix): ?string
    {
        if (! is_dir($directory)) {
            return null;
        }

        $files = collect(File::files($directory))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), $suffix))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        return $files->isEmpty() ? null : $files->first()->getPathname();
    }
}
