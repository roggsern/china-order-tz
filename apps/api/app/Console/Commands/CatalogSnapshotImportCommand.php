<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogFoundationSnapshotExporter;
use App\Services\Catalog\CatalogFoundationSnapshotImporter;
use Illuminate\Console\Command;
use Throwable;

class CatalogSnapshotImportCommand extends Command
{
    protected $signature = 'catalog:snapshot-import
                            {path : Path to catalog-foundation-snapshot JSON file}
                            {--execute : Persist changes (default is dry-run)}
                            {--mode=upsert : upsert|replace-foundation}
                            {--scope=china-admin : Snapshot scope (china-admin)}
                            {--allow-env=local : Comma-separated APP_ENV values allowed for --execute}
                            {--force-env : Override environment protection}';

    protected $description = 'Import a catalog foundation snapshot (dry-run by default; no production writes without --force-env).';

    public function handle(CatalogFoundationSnapshotImporter $importer): int
    {
        $path = (string) $this->argument('path');
        $execute = (bool) $this->option('execute');
        $mode = (string) $this->option('mode');
        $scope = (string) $this->option('scope');
        $forceEnv = (bool) $this->option('force-env');
        $allowEnvOption = (string) $this->option('allow-env');
        $allowEnvs = array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', $allowEnvOption),
        )));

        if (! $execute) {
            $this->warn('Dry run — no database writes. Pass --execute to apply.');
        }

        try {
            $result = $importer->import(
                path: $path,
                execute: $execute,
                mode: $mode,
                scope: $scope !== '' ? $scope : CatalogFoundationSnapshotExporter::SCOPE_CHINA_ADMIN,
                allowEnvs: $allowEnvs !== [] ? $allowEnvs : ['local'],
                forceEnv: $forceEnv,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($result['dry_run'] ? 'Catalog foundation snapshot import plan' : 'Catalog foundation snapshot import applied');
        $this->line('  Path: '.$result['path']);
        $this->line('  Mode: '.$result['mode']);
        $this->line('  Scope: '.$result['scope']);

        foreach ($result['counts'] as $table => $actions) {
            $parts = [];
            foreach ($actions as $action => $count) {
                if ($count > 0) {
                    $parts[] = "{$action}={$count}";
                }
            }
            $this->line('  '.$table.': '.($parts !== [] ? implode(', ', $parts) : 'no changes'));
        }

        foreach ($result['warnings'] as $warning) {
            $this->warn('  '.$warning);
        }

        if ($result['dry_run']) {
            $this->comment('Re-run with --execute to persist changes (blocked in production unless --force-env).');
        }

        return self::SUCCESS;
    }
}
