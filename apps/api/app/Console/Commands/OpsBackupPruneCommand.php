<?php

namespace App\Console\Commands;

use App\Support\Ops\Backup\BackupPaths;
use App\Support\Ops\Backup\BackupRetention;
use App\Support\Ops\Backup\BackupVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class OpsBackupPruneCommand extends Command
{
    protected $signature = 'ops:backup-prune
        {--confirm : Actually delete files matched by retention (required for destructive prune)}
        {--skip-verify : Skip verifying latest backups before destructive prune}';

    protected $description = 'Apply backup retention (dry-run by default; pass --confirm to delete).';

    public function handle(
        BackupRetention $retention,
        BackupPaths $paths,
        BackupVerifier $verifier,
    ): int {
        if (! config('backup.enabled')) {
            $this->warn('Backups disabled (BACKUP_ENABLED=false).');

            return self::SUCCESS;
        }

        try {
            $paths->ensureLayout();
            $dryRun = ! $this->option('confirm');

            if (! $dryRun && ! $this->option('skip-verify')) {
                $daily = $paths->tier('daily');
                $db = $this->newestMatching($daily, 'database.sql.gz');
                $media = $this->newestMatching($daily, 'media.tar.gz');

                foreach (array_filter([$db, $media]) as $target) {
                    $verifier->assertOk($target);
                }
            }

            $pruned = $retention->pruneAll(dryRun: $dryRun);

            if ($dryRun) {
                $this->warn('Dry run (default): no files deleted. Pass --confirm to prune.');
                foreach ($pruned as $path) {
                    $this->line('Would prune: '.basename($path));
                }
                $this->info('Would prune '.count($pruned).' file(s).');

                return self::SUCCESS;
            }

            foreach ($pruned as $path) {
                $this->line('Pruned: '.basename($path));
            }
            $this->info('Pruned '.count($pruned).' backup file(s).');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Backup prune failed.');
            report($e);

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
