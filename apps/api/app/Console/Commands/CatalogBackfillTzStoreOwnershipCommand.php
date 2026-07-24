<?php

namespace App\Console\Commands;

use App\Services\Catalog\TzLocalStoreOwnershipBackfill;
use Illuminate\Console\Command;

class CatalogBackfillTzStoreOwnershipCommand extends Command
{
    protected $signature = 'catalog:backfill-tz-store-ownership
                            {--execute : Apply assignments (default is dry-run)}
                            {--store-id= : Explicit store UUID for assignment}
                            {--include-listed : Allow active/out_of_stock products to receive store_id}
                            {--include-without-category-store : Allow explicit store assignment without category.store_id}';

    protected $description = 'Safely backfill store_id for TZ_LOCAL orphan products with reversible audit logging.';

    public function handle(TzLocalStoreOwnershipBackfill $backfill): int
    {
        $dryRun = ! (bool) $this->option('execute');

        if ($dryRun) {
            $this->warn('Dry run — no product rows will be updated. Pass --execute to apply.');
        }

        $result = $backfill->backfill([
            'dry_run' => $dryRun,
            'store_id' => $this->option('store-id'),
            'include_listed' => (bool) $this->option('include-listed'),
            'include_without_category_store' => (bool) $this->option('include-without-category-store'),
        ]);

        $this->info($dryRun ? 'Backfill plan' : 'Backfill applied');
        $this->line('  Batch id: '.$result['batch_id']);
        $this->line('  Assigned: '.$result['assigned']);
        $this->line('  Skipped: '.$result['skipped']);

        if ($result['rows'] !== []) {
            $this->table(
                ['Product id', 'Action', 'Store id', 'Reason'],
                collect($result['rows'])->map(fn (array $row) => [
                    $row['product_id'],
                    $row['action'],
                    $row['assigned_store_id'] ?? '—',
                    $row['reason'] ?? '—',
                ])->all(),
            );
        }

        if ($dryRun && $result['assigned'] > 0) {
            $this->comment('Re-run with --execute to persist assignments and write product_store_backfill_logs.');
        }

        if (! $dryRun && $result['assigned'] > 0) {
            $this->comment('Rollback: php artisan catalog:rollback-tz-store-ownership '.$result['batch_id'].' --execute');
        }

        return self::SUCCESS;
    }
}
