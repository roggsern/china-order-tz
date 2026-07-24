<?php

namespace App\Support\ProductMedia;

use App\Enums\ProductMediaType;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only projection of legacy product_images rows into the product_media API shape.
 * Does not persist rows or move files.
 */
final class LegacyProductMediaBridge
{
    /**
     * @return Collection<int, ProductMedia>
     */
    public static function fromProductImages(Product $product): Collection
    {
        return $product->images()
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->map(fn (ProductImage $image) => self::toProductMedia($image));
    }

    public static function toProductMedia(ProductImage $image): ProductMedia
    {
        $url = $image->path
            ? Storage::disk('public')->url($image->path)
            : '';

        $media = new ProductMedia([
            'product_id' => $image->product_id,
            'type' => ProductMediaType::Image,
            'url' => $url,
            'thumbnail_url' => $url,
            'alt_text' => $image->alt_text,
            'title' => null,
            'sort_order' => (int) $image->sort_order,
            'is_primary' => (bool) $image->is_primary,
            'is_active' => true,
        ]);

        $media->id = $image->id;
        $media->created_at = $image->created_at;
        $media->updated_at = $image->updated_at;
        $media->setAttribute('legacy_bridge', true);

        return $media;
    }
}
