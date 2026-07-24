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
 * Stores a product image file once and writes both legacy and catalog rows.
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
     * }  $metadata
     */
    public function storeUploadedImage(
        UploadedFile $file,
        Product $product,
        array $metadata = [],
    ): ProductImageWriteSyncResult {
        $path = SecureImageUpload::storePublic($file, 'products');
        $url = Storage::disk('public')->url($path);

        return DB::transaction(function () use ($product, $path, $url, $metadata) {
            $sortOrder = (int) ($metadata['sort_order'] ?? (
                (int) $product->media()->max('sort_order') + 1
            ));
            $isPrimary = (bool) ($metadata['is_primary'] ?? false);

            if (! $product->media()->images()->exists()) {
                $isPrimary = true;
            }

            if ($isPrimary) {
                ProductImage::query()
                    ->where('product_id', $product->id)
                    ->update(['is_primary' => false]);
                $product->media()->images()->update(['is_primary' => false]);
            }

            $legacyImage = ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $path,
                'alt_text' => $metadata['alt_text'] ?? null,
                'sort_order' => $sortOrder,
                'is_primary' => $isPrimary,
            ]);

            $catalogMedia = ProductMedia::query()->create([
                'product_id' => $product->id,
                'type' => ProductMediaType::Image,
                'url' => $url,
                'thumbnail_url' => $url,
                'alt_text' => $metadata['alt_text'] ?? null,
                'title' => $metadata['title'] ?? null,
                'sort_order' => $sortOrder,
                'is_primary' => $isPrimary,
                'is_active' => $metadata['is_active'] ?? true,
            ]);

            return new ProductImageWriteSyncResult($legacyImage, $catalogMedia);
        });
    }
}
