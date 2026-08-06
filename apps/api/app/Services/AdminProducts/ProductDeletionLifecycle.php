<?php

namespace App\Services\AdminProducts;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Permanently delete a soft-deleted product and owned runtime rows.
     * Physical media files are removed only after the DB transaction commits,
     * and only when the storage object is not still referenced by another product.
     *
     * @return array{deleted_files: int, missing_files: int, shared_files_skipped: int, file_errors: list<string>}
     */
    public function forceDelete(Product $product): array
    {
        $mediaPlan = $this->collectExclusiveMediaStoragePaths($product);

        DB::transaction(function () use ($product): void {
            /** @var Product $locked */
            $locked = Product::onlyTrashed()->whereKey($product->id)->lockForUpdate()->firstOrFail();

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

        return $this->deleteExclusiveMediaFiles(
            $mediaPlan['paths'],
            $product->id,
            $mediaPlan['shared_skipped'],
        );
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

    /**
     * @return array{paths: list<string>, shared_skipped: int}
     */
    private function collectExclusiveMediaStoragePaths(Product $product): array
    {
        $urls = ProductMedia::withTrashed()
            ->where('product_id', $product->id)
            ->pluck('url')
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->unique()
            ->values();

        $paths = [];
        $sharedSkipped = 0;

        foreach ($urls as $url) {
            $path = $this->storagePathFromPublicUrl((string) $url);
            if ($path === null) {
                continue;
            }

            $stillReferenced = ProductMedia::withTrashed()
                ->where('url', $url)
                ->where('product_id', '!=', $product->id)
                ->exists();

            if ($stillReferenced) {
                $sharedSkipped++;
                continue;
            }

            $paths[] = $path;
        }

        $legacyPaths = ProductImage::withTrashed()
            ->where('product_id', $product->id)
            ->pluck('path')
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->unique()
            ->values()
            ->all();

        foreach ($legacyPaths as $legacyPath) {
            $stillReferenced = ProductImage::withTrashed()
                ->where('path', $legacyPath)
                ->where('product_id', '!=', $product->id)
                ->exists();

            if ($stillReferenced) {
                $sharedSkipped++;
                continue;
            }

            $paths[] = $legacyPath;
        }

        return [
            'paths' => array_values(array_unique($paths)),
            'shared_skipped' => $sharedSkipped,
        ];
    }

    /**
     * @param  list<string>  $paths
     * @return array{deleted_files: int, missing_files: int, shared_files_skipped: int, file_errors: list<string>}
     */
    private function deleteExclusiveMediaFiles(array $paths, string $productId, int $sharedSkipped = 0): array
    {
        $deleted = 0;
        $missing = 0;
        $errors = [];

        foreach ($paths as $path) {
            try {
                if (! Storage::disk('public')->exists($path)) {
                    $missing++;
                    continue;
                }
                Storage::disk('public')->delete($path);
                $deleted++;
            } catch (\Throwable $exception) {
                $errors[] = $path.': '.$exception->getMessage();
                Log::warning('product_force_delete_media_cleanup_failed', [
                    'product_id' => $productId,
                    'path' => $path,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'deleted_files' => $deleted,
            'missing_files' => $missing,
            'shared_files_skipped' => $sharedSkipped,
            'file_errors' => $errors,
        ];
    }

    private function storagePathFromPublicUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            // Relative path already, e.g. products/uuid.jpg
            if (str_starts_with($url, 'products/')) {
                return $url;
            }

            return null;
        }

        $needle = '/storage/';
        $pos = strpos($path, $needle);
        if ($pos === false) {
            return null;
        }

        $relative = ltrim(substr($path, $pos + strlen($needle)), '/');

        return $relative !== '' ? $relative : null;
    }
}
