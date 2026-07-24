<?php

namespace App\Console\Commands;

use App\Services\Catalog\CommerceChannelBackfillService;
use Illuminate\Console\Command;

class CatalogBackfillCommerceChannelCommand extends Command
{
    protected $signature = 'catalog:backfill-commerce-channel
                            {--execute : Apply assignments (default is dry-run)}';

    protected $description = 'Safely backfill commerce_channel_id for legacy imported_from_china products.';

    public function handle(CommerceChannelBackfillService $backfill): int
    {
        $dryRun = ! (bool) $this->option('execute');

        if ($dryRun) {
            $this->warn('Dry run — no product rows will be updated. Pass --execute to apply.');
        }

        $result = $backfill->backfill([
            'dry_run' => $dryRun,
        ]);

        $this->info($dryRun ? 'Backfill plan' : 'Backfill applied');
        $this->line('  Batch id: '.$result['batch_id']);
        $this->line('  Assigned: '.$result['assigned']);
        $this->line('  Skipped: '.$result['skipped']);

        if ($result['rows'] !== []) {
            $this->table(
                ['Product id', 'Action', 'Channel id', 'Reason'],
                collect($result['rows'])->map(fn (array $row) => [
                    $row['product_id'],
                    $row['action'],
                    $row['assigned_channel_id'] ?? '—',
                    $row['reason'] ?? '—',
                ])->all(),
            );
        }

        if ($dryRun && $result['assigned'] > 0) {
            $this->comment('Re-run with --execute to persist assignments and write product_channel_backfill_logs.');
        }

        if (! $dryRun && $result['assigned'] > 0) {
            $this->comment('Rollback: php artisan catalog:rollback-commerce-channel '.$result['batch_id'].' --execute');
        }

        return self::SUCCESS;
    }
}
