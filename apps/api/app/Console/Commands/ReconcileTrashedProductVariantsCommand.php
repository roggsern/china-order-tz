<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AdminProducts\ProductDeletionLifecycle;
use Illuminate\Console\Command;

/**
 * Soft-delete active variants still attached to soft-deleted products (legacy orphans).
 */
class ReconcileTrashedProductVariantsCommand extends Command
{
    protected $signature = 'catalog:reconcile-trashed-product-variants
                            {--product= : Soft-deleted product UUID}
                            {--force : Required to apply changes}
                            {--dry-run : Report only}';

    protected $description = 'Soft-delete orphan active variants whose parent product is soft-deleted';

    public function handle(ProductDeletionLifecycle $lifecycle): int
    {
        $productId = $this->option('product');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->error('Refusing to run without --force (or use --dry-run).');

            return self::FAILURE;
        }

        $query = Product::onlyTrashed()->orderBy('deleted_at');
        if (filled($productId)) {
            $query->whereKey($productId);
        }

        $products = $query->get();
        if ($products->isEmpty()) {
            $this->warn('No soft-deleted products matched.');

            return self::SUCCESS;
        }

        $totalOrphans = 0;

        foreach ($products as $product) {
            $orphanCount = $product->variants()->count();
            $this->line(sprintf(
                '%s  %s  deleted_at=%s  orphan_active_variants=%d',
                $product->id,
                $product->name,
                $product->deleted_at?->toIso8601String() ?? 'n/a',
                $orphanCount,
            ));

            if ($orphanCount === 0) {
                continue;
            }

            $totalOrphans += $orphanCount;

            if ($dryRun) {
                continue;
            }

            $deleted = $lifecycle->softDeleteOrphanActiveVariants($product);
            $this->info("  soft-deleted {$deleted} orphan variant(s)");
        }

        $this->comment($dryRun
            ? "Dry run complete. {$totalOrphans} orphan active variant(s) would be soft-deleted."
            : "Done. Processed orphan variants across {$products->count()} trashed product(s).");

        return self::SUCCESS;
    }
}
