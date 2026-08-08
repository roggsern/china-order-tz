<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogFoundationSnapshotExporter;
use Illuminate\Console\Command;
use Throwable;

class CatalogSnapshotExportCommand extends Command
{
    protected $signature = 'catalog:snapshot-export
                            {--path= : Optional output file or directory}
                            {--scope=china-admin : Snapshot scope (china-admin)}';

    protected $description = 'Export a read-only JSON snapshot of China admin catalog foundation tables.';

    public function handle(CatalogFoundationSnapshotExporter $exporter): int
    {
        $scope = (string) $this->option('scope');
        $path = $this->option('path');
        $path = is_string($path) && trim($path) !== '' ? trim($path) : null;

        try {
            $result = $exporter->exportToFile($path, $scope);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        /** @var array<string, int> $counts */
        $counts = $result['snapshot']['counts'];

        $this->info('Catalog foundation snapshot exported (read-only; no database writes).');
        $this->line('  Scope: '.$result['snapshot']['scope']);
        $this->line('  Path: '.$result['path']);
        $this->line('  Bytes: '.$result['bytes']);
        $this->line('  SHA-256: '.$result['checksum']);
        $this->line('  Counts:');

        foreach ($counts as $table => $count) {
            $this->line("    - {$table}: {$count}");
        }

        return self::SUCCESS;
    }
}
