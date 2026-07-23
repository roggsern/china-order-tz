<?php

namespace App\Support\Ops\Backup;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

final class BackupRetention
{
    public function __construct(
        private readonly BackupPaths $paths,
    ) {}

    /**
     * @param  list<string>  $dailyFiles
     * @return array{promoted: list<string>, pruned: list<string>}
     */
    public function apply(array $dailyFiles = [], bool $dryRun = false): array
    {
        $this->paths->ensureLayout();
        $promoted = $this->promote($dailyFiles, $dryRun);
        $pruned = $this->pruneAll($dryRun);

        return compact('promoted', 'pruned');
    }

    /**
     * @param  list<string>  $dailyFiles
     * @return list<string>
     */
    public function promote(array $dailyFiles, bool $dryRun = false): array
    {
        $promoted = [];
        $now = now();

        foreach ($dailyFiles as $file) {
            if (! is_file($file)) {
                continue;
            }

            $basename = basename($file);

            if ($now->dayOfWeek === CarbonInterface::SUNDAY) {
                $target = $this->paths->tier('weekly').DIRECTORY_SEPARATOR.$basename;
                if (! is_file($target)) {
                    if ($dryRun) {
                        $promoted[] = $target;
                    } elseif (@copy($file, $target)) {
                        @chmod($target, 0600);
                        $promoted[] = $target;
                    }
                }
            }

            if ($now->day === 1) {
                $target = $this->paths->tier('monthly').DIRECTORY_SEPARATOR.$basename;
                if (! is_file($target)) {
                    if ($dryRun) {
                        $promoted[] = $target;
                    } elseif (@copy($file, $target)) {
                        @chmod($target, 0600);
                        $promoted[] = $target;
                    }
                }
            }
        }

        return $promoted;
    }

    /**
     * @return list<string>
     */
    public function pruneAll(bool $dryRun = false): array
    {
        $pruned = [];
        foreach (['daily', 'weekly', 'monthly'] as $tier) {
            $keep = max(0, (int) config("backup.retention.{$tier}", 0));
            $pruned = array_merge($pruned, $this->pruneTier($tier, $keep, $dryRun));
        }

        return $pruned;
    }

    /**
     * @return list<string>
     */
    public function pruneTier(string $tier, int $keep, bool $dryRun = false): array
    {
        $dir = $this->paths->tier($tier);
        if (! is_dir($dir)) {
            return [];
        }

        $files = collect(File::files($dir))
            ->filter(fn ($f) => $f->isFile())
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        $remove = $keep <= 0 ? $files : $files->slice($keep);

        $pruned = [];
        foreach ($remove as $file) {
            $path = $file->getPathname();
            if ($dryRun) {
                $pruned[] = $path;
                continue;
            }

            if (@unlink($path)) {
                $pruned[] = $path;
                Log::info('ops.backup.pruned', [
                    'tier' => $tier,
                    'file' => basename($path),
                ]);
            }
        }

        return $pruned;
    }
}
