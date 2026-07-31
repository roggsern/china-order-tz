<?php

namespace App\Services\ProductMedia;

use App\Enums\ProductMediaType;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use App\Support\Security\SecureImageUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Stores a product image file once and writes catalog product_media only.
 *
 * product_images is legacy read fallback — new uploads must not dual-write.
 * Variant-bound uploads remain catalog-only (legacy table has no variant binding).
 *
 * Orphan file policy:
 * - Soft-delete of ProductMedia / ProductImage does NOT delete storage files.
 * - Aggressive disk GC is out of scope until a dedicated lifecycle job exists.
 * - Files may remain after DB soft-delete; treat as acceptable until GC is designed.
 */
class ProductImageWriteSyncService
{
    /**
     * @param  array{
     *     alt_text?: string|null,
     *     title?: string|null,
     *     sort_order?: int,
     *     is_primary?: bool,
     *     is_active?: bool,
     *     product_variant_id?: string|null,
     * }  $metadata
     */
    public function storeUploadedImage(
        UploadedFile $file,
        Product $product,
        array $metadata = [],
    ): ProductImageWriteSyncResult {
        $path = SecureImageUpload::storePublic($file, 'products');
        $url = Storage::disk('public')->url($path);
        $variantId = $metadata['product_variant_id'] ?? null;

        return DB::transaction(function () use ($product, $path, $url, $metadata, $variantId) {
            $sortOrder = (int) ($metadata['sort_order'] ?? (
                (int) $this->mediaScope($product, $variantId)->max('sort_order') + 1
            ));
            $isPrimary = (bool) ($metadata['is_primary'] ?? false);

            if (! $this->mediaScope($product, $variantId)->images()->exists()) {
                $isPrimary = true;
            }

            if ($isPrimary) {
                $this->clearPrimaryFlags($product->id, $variantId);
            }

            $catalogMedia = ProductMedia::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'type' => ProductMediaType::Image,
                'url' => $url,
                'thumbnail_url' => $url,
                'alt_text' => $metadata['alt_text'] ?? null,
                'title' => $metadata['title'] ?? null,
                'sort_order' => $sortOrder,
                'is_primary' => $isPrimary,
                'is_active' => $metadata['is_active'] ?? true,
            ]);

            return new ProductImageWriteSyncResult(
                legacyImage: null,
                catalogMedia: $catalogMedia,
                storagePath: $path,
            );
        });
    }

    private function clearPrimaryFlags(string $productId, ?string $variantId): void
    {
        if ($variantId === null) {
            // Keep legacy primary flags coherent when catalog becomes authoritative.
            ProductImage::query()
                ->where('product_id', $productId)
                ->update(['is_primary' => false]);

            ProductMedia::query()
                ->where('product_id', $productId)
                ->whereNull('product_variant_id')
                ->where('type', ProductMediaType::Image)
                ->update(['is_primary' => false]);

            return;
        }

        ProductMedia::query()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->where('type', ProductMediaType::Image)
            ->update(['is_primary' => false]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ProductMedia, \App\Models\Product>|\Illuminate\Database\Eloquent\Builder<\App\Models\ProductMedia>
     */
    private function mediaScope(Product $product, ?string $variantId)
    {
        if ($variantId === null) {
            return $product->media();
        }

        return ProductMedia::query()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variantId);
    }
}
