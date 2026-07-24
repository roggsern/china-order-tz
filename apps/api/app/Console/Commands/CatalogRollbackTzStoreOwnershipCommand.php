<?php

namespace App\Console\Commands;

use App\Services\Catalog\TzLocalStoreOwnershipBackfill;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CatalogRollbackTzStoreOwnershipCommand extends Command
{
    protected $signature = 'catalog:rollback-tz-store-ownership
                            {batch_id : Backfill batch UUID from product_store_backfill_logs}
                            {--execute : Restore previous store_id values (default is dry-run)}';

    protected $description = 'Rollback a TZ store ownership backfill batch using audit logs.';

    public function handle(TzLocalStoreOwnershipBackfill $backfill): int
    {
        $batchId = trim((string) $this->argument('batch_id'));
        $dryRun = ! (bool) $this->option('execute');

        if ($dryRun) {
            $this->warn('Dry run — no product rows will be updated. Pass --execute to rollback.');
        }

        try {
            $result = $backfill->rollback($batchId, $dryRun);
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first() ?? 'Rollback failed.');

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Rollback plan' : 'Rollback applied');
        $this->line('  Batch id: '.$batchId);
        $this->line('  Restored: '.$result['restored']);
        $this->line('  Skipped: '.$result['skipped']);

        return self::SUCCESS;
    }
}
