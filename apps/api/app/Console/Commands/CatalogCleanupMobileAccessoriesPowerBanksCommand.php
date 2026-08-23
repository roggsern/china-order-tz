<?php

namespace App\Console\Commands;

use App\Services\Catalog\MobileAccessoriesTaxonomyCleanupService;
use Illuminate\Console\Command;
use Throwable;

class CatalogCleanupMobileAccessoriesPowerBanksCommand extends Command
{
    protected $signature = 'catalog:cleanup-mobile-accessories-power-banks
                            {--execute : Apply competing Power Banks cleanup (default is dry-run)}';

    protected $description = 'Reuse Phones & Tablets → Phone Accessories → Power Banks and retire competing Power Banks categories/CPTs.';

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
        $this->line('Canonical category: '.($result['canonical_category_id'] ?? '—'));
        $this->line('Canonical CPT: '.($result['canonical_product_type_id'] ?? '—'));
        $this->newLine();

        $this->line('Category duplicates: '.count($result['competing']));
        foreach ($result['competing'] as $node) {
            $this->line(sprintf(
                '  category %s id=%s products=%d cpts=%d maps=%d/%d children=%d',
                $node['slug'],
                $node['id'],
                $node['product_count'],
                $node['catalog_product_type_count'],
                $node['import_map_source_count'],
                $node['import_map_target_count'],
                $node['child_count'],
            ));
        }

        $this->newLine();
        $this->line('CPT duplicates: '.count($result['competing_product_types']));
        foreach ($result['competing_product_types'] as $type) {
            $compatibility = $type['attribute_compatibility']['compatible'] ? 'compatible' : 'SKIP incompatible attributes';
            $this->line(sprintf(
                '  %s → %s → CPT %s',
                $type['department_slug'] ?? 'unknown-department',
                $type['category_slug'] ?? 'unknown-category',
                $type['slug'],
            ));
            $this->line(sprintf(
                '    competing category=%s id=%s',
                $type['category_slug'] ?? '—',
                $type['category_id'] ?? '—',
            ));
            $this->line(sprintf(
                '    competing CPT=%s id=%s products=%d',
                $type['slug'],
                $type['id'],
                $type['product_count'],
            ));
            $this->line(sprintf(
                '    target category=%s id=%s',
                $type['target_category_slug'],
                $type['target_category_id'],
            ));
            $this->line(sprintf(
                '    target CPT=%s id=%s',
                $type['target_product_type_slug'],
                $type['target_product_type_id'],
            ));
            $this->line(sprintf(
                '    attributes=%s missing_required=[%s] extra=[%s]',
                $compatibility,
                implode(', ', $type['attribute_compatibility']['missing_required_on_competing']),
                implode(', ', $type['attribute_compatibility']['extra_on_competing']),
            ));
            $this->line(sprintf(
                '    parent import maps source=%d target=%d (preserved, not repointed)',
                $type['import_map_source_count'],
                $type['import_map_target_count'],
            ));
        }

        $this->newLine();
        $this->line('Planned product migrations: '.count($result['planned_migrations']));
        foreach ($result['planned_migrations'] as $migration) {
            $this->line(sprintf(
                '  %s [%s] %s → %s | CPT %s → %s%s',
                $migration['product_name'],
                $migration['product_id'],
                $migration['from_category_slug'] ?? $migration['from_category_id'],
                $migration['to_category_slug'],
                $migration['from_product_type_slug'],
                $migration['to_product_type_slug'],
                $migration['will_migrate'] ? '' : ' [SKIP]',
            ));
        }

        $this->newLine();
        $this->line('Deactivated categories: '.count($result['deactivated_category_ids']));
        $this->line('Deactivated CPTs: '.count($result['deactivated_product_type_ids']));
        $this->line('Skipped categories: '.count($result['skipped_category_ids']));
        $this->line('Skipped CPTs: '.count($result['skipped_product_type_ids']));

        foreach ($result['steps'] as $step) {
            $this->line('  - '.$step);
        }

        if ($dryRun) {
            $this->comment('Re-run with --execute to persist changes after review.');
        }

        return self::SUCCESS;
    }
}
