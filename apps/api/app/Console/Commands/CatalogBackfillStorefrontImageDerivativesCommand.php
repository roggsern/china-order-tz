<?php

namespace App\Console\Commands;

use App\Services\ProductMedia\StorefrontImageDerivativeBackfillService;
use Illuminate\Console\Command;

class CatalogBackfillStorefrontImageDerivativesCommand extends Command
{
    protected $signature = 'catalog:backfill-storefront-image-derivatives
                            {--product= : Limit to a single product UUID}
                            {--limit= : Max pending media rows (display_url IS NULL) to consider}
                            {--execute : Write derivatives and update display_url (default is dry-run)}';

    protected $description = 'Idempotently generate missing storefront display WebP derivatives for product_media images.';

    public function handle(StorefrontImageDerivativeBackfillService $backfill): int
    {
        $dryRun = ! (bool) $this->option('execute');
        $productId = $this->option('product');
        $limit = $this->option('limit');

        if ($dryRun) {
            $this->warn('Dry run — no files or DB rows will be updated. Pass --execute to apply.');
        }

        $result = $backfill->backfill([
            'dry_run' => $dryRun,
            'product_id' => filled($productId) ? (string) $productId : null,
            'limit' => filled($limit) ? (int) $limit : null,
        ]);

        $this->info($dryRun ? 'Backfill plan' : 'Backfill applied');
        $this->line('  Processed: '.$result['processed']);
        $this->line('  Generated: '.$result['generated']);
        $this->line('  Linked existing: '.$result['linked_existing']);
        $this->line('  Skipped: '.$result['skipped']);
        $this->line('  Failed: '.$result['failed']);

        if ($result['rows'] !== []) {
            $this->table(
                ['Media id', 'Product id', 'Action', 'Detail'],
                collect($result['rows'])->map(fn (array $row) => [
                    $row['media_id'],
                    $row['product_id'],
                    $row['action'],
                    $row['detail'],
                ])->all(),
            );
        }

        if ($dryRun && ($result['generated'] > 0 || $result['linked_existing'] > 0)) {
            $this->comment('Re-run with --execute to write derivatives and set product_media.display_url.');
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
