<?php

namespace App\Services\ProductMedia;

use App\Enums\ProductMediaType;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductPrimarySyncService
{
    public function setPrimaryFromLegacyImage(ProductImage $image): ProductImage
    {
        return DB::transaction(function () use ($image) {
            $this->clearPrimaryFlags($image->product_id);

            $image->update(['is_primary' => true]);

            $pairedMedia = $this->findMediaForLegacyImage($image);
            if ($pairedMedia !== null) {
                $pairedMedia->update([
                    'is_primary' => true,
                    'is_active' => true,
                ]);
            }

            return $image->fresh();
        });
    }

    public function setPrimaryFromCatalogMedia(ProductMedia $media): ProductMedia
    {
        return DB::transaction(function () use ($media) {
            $this->clearPrimaryFlags($media->product_id);

            $media->update([
                'is_primary' => true,
                'is_active' => true,
            ]);

            $pairedImage = $this->findLegacyImageForMedia($media);
            if ($pairedImage !== null) {
                $pairedImage->update(['is_primary' => true]);
            }

            return $media->fresh();
        });
    }

    public function clearPrimaryFromCatalogMedia(ProductMedia $media): ProductMedia
    {
        return DB::transaction(function () use ($media) {
            $media->update(['is_primary' => false]);

            $pairedImage = $this->findLegacyImageForMedia($media);
            if ($pairedImage !== null) {
                $pairedImage->update(['is_primary' => false]);
            }

            return $media->fresh();
        });
    }

    private function clearPrimaryFlags(string $productId): void
    {
        ProductImage::query()
            ->where('product_id', $productId)
            ->update(['is_primary' => false]);

        ProductMedia::query()
            ->where('product_id', $productId)
            ->where('type', ProductMediaType::Image)
            ->update(['is_primary' => false]);
    }

    private function findMediaForLegacyImage(ProductImage $image): ?ProductMedia
    {
        if (! $image->path) {
            return null;
        }

        $url = Storage::disk('public')->url($image->path);

        return ProductMedia::query()
            ->where('product_id', $image->product_id)
            ->where('type', ProductMediaType::Image)
            ->where('url', $url)
            ->first();
    }

    private function findLegacyImageForMedia(ProductMedia $media): ?ProductImage
    {
        if (! $media->url) {
            return null;
        }

        return ProductImage::query()
            ->where('product_id', $media->product_id)
            ->get()
            ->first(function (ProductImage $image) use ($media): bool {
                if (! $image->path) {
                    return false;
                }

                return Storage::disk('public')->url($image->path) === $media->url;
            });
    }
}
