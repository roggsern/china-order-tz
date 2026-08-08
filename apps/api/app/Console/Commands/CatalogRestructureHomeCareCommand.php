<?php

namespace App\Console\Commands;

use App\Services\Catalog\HomeCareTaxonomyRestructureService;
use Illuminate\Console\Command;
use Throwable;

class CatalogRestructureHomeCareCommand extends Command
{
    protected $signature = 'catalog:restructure-home-care
                            {--execute : Apply Home Care restore/restructure (default is dry-run)}';

    protected $description = 'Restore and restructure soft-deleted Home Care China taxonomy (admin only; no storefront wiring).';

    public function handle(HomeCareTaxonomyRestructureService $service): int
    {
        $dryRun = ! (bool) $this->option('execute');

        if ($dryRun) {
            $this->warn('Dry run — no taxonomy rows will be updated. Pass --execute to apply.');
        }

        try {
            $result = $service->restructure(dryRun: $dryRun);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Home Care restructure plan' : 'Home Care restructure applied');
        $this->line('  Department id: '.$result['department_id']);
        $this->line('  Pest Control id: '.$result['pest_control_id']);
        $this->line('  Product type id: '.$result['product_type_id']);
        $this->line('  Duplicate category id: '.($result['removed_duplicate_category_id'] ?? '—'));

        foreach ($result['steps'] as $step) {
            $this->line('  - '.$step);
        }

        if ($result['created_category_slugs'] !== []) {
            $this->line('  Created category slugs: '.implode(', ', $result['created_category_slugs']));
        }

        if ($result['removed_attribute_slugs'] !== []) {
            $this->line('  Removed attribute mappings: '.implode(', ', $result['removed_attribute_slugs']));
        }

        if ($dryRun) {
            $this->comment('Re-run with --execute to persist changes.');
        } else {
            $this->comment('Storefront wiring intentionally unchanged (CatalogBible / crosswalk / featured collections).');
        }

        return self::SUCCESS;
    }
}
