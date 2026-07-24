<?php

namespace App\Console\Commands;

use App\Services\Catalog\ShippingOptionsBackfillService;
use Illuminate\Console\Command;

class CatalogRollbackShippingBackfillCommand extends Command
{
    protected $signature = 'catalog:rollback-shipping-backfill
                            {batch : Batch UUID from product_shipping_backfill_logs}
                            {--execute : Apply rollback (default is dry-run)}';

    protected $description = 'Rollback a shipping options backfill batch using audit logs.';

    public function handle(ShippingOptionsBackfillService $backfill): int
    {
        $batchId = (string) $this->argument('batch');
        $dryRun = ! (bool) $this->option('execute');

        if ($dryRun) {
            $this->warn('Dry run — no shipping options will be removed. Pass --execute to apply rollback.');
        }

        $result = $backfill->rollback($batchId, $dryRun);

        $this->info($dryRun ? 'Rollback plan' : 'Rollback applied');
        $this->line('  Batch id: '.$batchId);
        $this->line('  Restored: '.$result['restored']);
        $this->line('  Skipped: '.$result['skipped']);

        return self::SUCCESS;
    }
}
