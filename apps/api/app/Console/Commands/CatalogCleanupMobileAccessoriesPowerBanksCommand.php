<?php

namespace App\Console\Commands;

use App\Services\Catalog\MobileAccessoriesTaxonomyCleanupService;
use Illuminate\Console\Command;
use Throwable;

class CatalogCleanupMobileAccessoriesPowerBanksCommand extends Command
{
    protected $signature = 'catalog:cleanup-mobile-accessories-power-banks
                            {--execute : Apply competing Power Banks cleanup (default is dry-run)}';

    protected $description = 'Reuse Phones & Tablets → Phone Accessories → Power Banks and deactivate competing Consumer Electronics Power Banks nodes.';

    public function handle(MobileAccessoriesTaxonomyCleanupService $service): int
    {
        $dryRun = ! (bool) $this->option('execute');

        if ($dryRun) {
            $this->warn('Dry run — no taxonomy rows will be updated. Pass --execute to apply.');
        }

        try {
            $result = $service->cleanup(dryRun: $dryRun);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Mobile accessories Power Banks cleanup plan' : 'Mobile accessories Power Banks cleanup applied');
        $this->line('  Canonical category id: '.($result['canonical_category_id'] ?? '—'));
        $this->line('  Competing nodes: '.count($result['competing']));
        $this->line('  Deactivated: '.count($result['deactivated_category_ids']));
        $this->line('  Skipped: '.count($result['skipped_category_ids']));

        foreach ($result['steps'] as $step) {
            $this->line('  - '.$step);
        }

        foreach ($result['competing'] as $node) {
            $this->line(sprintf(
                '  node %s products=%d cpts=%d maps=%d/%d children=%d',
                $node['slug'],
                $node['product_count'],
                $node['catalog_product_type_count'],
                $node['import_map_source_count'],
                $node['import_map_target_count'],
                $node['child_count'],
            ));
        }

        if ($dryRun) {
            $this->comment('Re-run with --execute to persist changes.');
        }

        return self::SUCCESS;
    }
}
