<?php

namespace App\Console\Commands;

use App\Services\Catalog\CommerceChannelBackfillService;
use Illuminate\Console\Command;

class CatalogRollbackCommerceChannelCommand extends Command
{
    protected $signature = 'catalog:rollback-commerce-channel
                            {batch : Batch UUID from product_channel_backfill_logs}
                            {--execute : Apply rollback (default is dry-run)}';

    protected $description = 'Rollback a commerce channel backfill batch using audit logs.';

    public function handle(CommerceChannelBackfillService $backfill): int
    {
        $batchId = (string) $this->argument('batch');
        $dryRun = ! (bool) $this->option('execute');

        if ($dryRun) {
            $this->warn('Dry run — no product rows will be updated. Pass --execute to apply rollback.');
        }

        $result = $backfill->rollback($batchId, $dryRun);

        $this->info($dryRun ? 'Rollback plan' : 'Rollback applied');
        $this->line('  Batch id: '.$batchId);
        $this->line('  Restored: '.$result['restored']);
        $this->line('  Skipped: '.$result['skipped']);

        return self::SUCCESS;
    }
}
