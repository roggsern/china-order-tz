<?php

namespace App\Console\Commands;

use App\Services\Catalog\ShippingOptionsBackfillService;
use Illuminate\Console\Command;

class CatalogBackfillShippingOptionsCommand extends Command
{
    protected $signature = 'catalog:backfill-shipping-options
                            {--execute : Apply backfill (default is dry-run)}';

    protected $description = 'Backfill product_shipping_options from legacy air/sea columns for CHINA_IMPORT products.';

    public function handle(ShippingOptionsBackfillService $backfill): int
    {
        $dryRun = ! (bool) $this->option('execute');

        if ($dryRun) {
            $this->warn('Dry run — no shipping options will be created. Pass --execute to apply.');
        }

        $result = $backfill->backfill([
            'dry_run' => $dryRun,
        ]);

        $this->info($dryRun ? 'Backfill plan' : 'Backfill applied');
        $this->line('  Batch id: '.$result['batch_id']);
        $this->line('  Backfilled: '.$result['backfilled']);
        $this->line('  Skipped: '.$result['skipped']);

        if ($result['rows'] !== []) {
            $this->table(
                ['Product id', 'Action', 'Planned modes', 'Reason'],
                collect($result['rows'])->map(fn (array $row) => [
                    $row['product_id'],
                    $row['action'],
                    implode(', ', $row['planned_modes']) ?: '—',
                    $row['reason'] ?? '—',
                ])->all(),
            );
        }

        if ($dryRun && $result['backfilled'] > 0) {
            $this->comment('Re-run with --execute to create options and write product_shipping_backfill_logs.');
        }

        if (! $dryRun && $result['backfilled'] > 0) {
            $this->comment('Rollback: php artisan catalog:rollback-shipping-backfill '.$result['batch_id'].' --execute');
        }

        return self::SUCCESS;
    }
}
