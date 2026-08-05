<?php

namespace App\Services\AdminProducts;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Soft-delete / restore / force-delete cascade for catalog products.
 *
 * SoftDeletes on children do not fire FK cascades; parent soft-delete must
 * explicitly soft-delete variants (and media) so active listings never see
 * orphan variant rows bound to a trashed product.
 */
class ProductDeletionLifecycle
{
    public function softDelete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $locked->variants()->get()->each(function (ProductVariant $variant): void {
                $variant->delete();
            });

            $locked->media()->get()->each(function (ProductMedia $media): void {
                $media->delete();
            });

            $locked->images()->get()->each(function (ProductImage $image): void {
                $image->delete();
            });

            $locked->delete();
        });
    }

    public function restore(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            /** @var Product $locked */
            $locked = Product::onlyTrashed()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $deletedAt = $locked->deleted_at;
            $locked->restore();

            // Restore children soft-deleted with (or after) the parent soft-delete.
            // Intentionally deleted variants from before product deletion keep an earlier deleted_at.
            $variantQuery = ProductVariant::onlyTrashed()->where('product_id', $locked->id);
            if ($deletedAt !== null) {
                $variantQuery->where('deleted_at', '>=', $deletedAt->copy()->subSeconds(2));
            }
            $variantQuery->get()->each(fn (ProductVariant $variant) => $variant->restore());

            $mediaQuery = ProductMedia::onlyTrashed()->where('product_id', $locked->id);
            if ($deletedAt !== null) {
                $mediaQuery->where('deleted_at', '>=', $deletedAt->copy()->subSeconds(2));
            }
            $mediaQuery->get()->each(fn (ProductMedia $media) => $media->restore());

            $imageQuery = ProductImage::onlyTrashed()->where('product_id', $locked->id);
            if ($deletedAt !== null) {
                $imageQuery->where('deleted_at', '>=', $deletedAt->copy()->subSeconds(2));
            }
            $imageQuery->get()->each(fn (ProductImage $image) => $image->restore());

            return $locked->fresh(['category', 'brand', 'inventory']) ?? $locked;
        });
    }

    public function forceDelete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            /** @var Product $locked */
            $locked = Product::onlyTrashed()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            // Force-delete soft-deleted children first so no dangling rows remain if
            // DB cascade timing differs; FK cascadeOnDelete also covers hard deletes.
            ProductVariant::withTrashed()
                ->where('product_id', $locked->id)
                ->get()
                ->each(fn (ProductVariant $variant) => $variant->forceDelete());

            ProductMedia::withTrashed()
                ->where('product_id', $locked->id)
                ->get()
                ->each(fn (ProductMedia $media) => $media->forceDelete());

            ProductImage::withTrashed()
                ->where('product_id', $locked->id)
                ->get()
                ->each(fn (ProductImage $image) => $image->forceDelete());

            $locked->forceDelete();
        });
    }

    /**
     * Soft-delete active variants still pointing at an already-trashed product
     * (legacy orphans created before cascade existed).
     *
     * @return int Number of variants soft-deleted
     */
    public function softDeleteOrphanActiveVariants(Product $trashedProduct): int
    {
        if (! $trashedProduct->trashed()) {
            return 0;
        }

        $count = 0;

        ProductVariant::query()
            ->where('product_id', $trashedProduct->id)
            ->orderBy('id')
            ->each(function (ProductVariant $variant) use (&$count): void {
                $variant->delete();
                $count++;
            });

        return $count;
    }
}
