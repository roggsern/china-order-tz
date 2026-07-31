<?php

namespace App\Services\ProductMedia;

use App\Enums\ProductMediaType;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Soft-delete sync between catalog product_media and legacy product_images.
 *
 * Orphan file policy:
 * - Soft-deletes clear DB rows (and paired legacy/catalog counterparts when URL-matched).
 * - Storage files are intentionally retained (no aggressive GC).
 * - Disk cleanup requires a future dedicated lifecycle/GC job.
 */
class ProductMediaDeleteSyncService
{
    public function deleteFromLegacyImage(ProductImage $image): void
    {
        DB::transaction(function () use ($image) {
            $pairedMedia = $this->findMediaForLegacyImage($image);

            $this->clearPrimaryOnTargets($image, $pairedMedia);

            $image->delete();

            if ($pairedMedia !== null) {
                $pairedMedia->delete();
            }
        });
    }

    public function deleteFromCatalogMedia(ProductMedia $media): void
    {
        DB::transaction(function () use ($media) {
            if ($media->type === ProductMediaType::Video) {
                $this->clearPrimaryOnMedia($media);
                $media->delete();

                return;
            }

            $pairedImage = $this->findLegacyImageForMedia($media);

            $this->clearPrimaryOnTargets($pairedImage, $media);

            $media->delete();

            if ($pairedImage !== null) {
                $pairedImage->delete();
            }
        });
    }

    private function clearPrimaryOnTargets(?ProductImage $image, ?ProductMedia $media): void
    {
        if ($image !== null && $image->is_primary) {
            $image->update(['is_primary' => false]);
        }

        if ($media !== null && $media->is_primary) {
            $media->update(['is_primary' => false]);
        }
    }

    private function clearPrimaryOnMedia(ProductMedia $media): void
    {
        if ($media->is_primary) {
            $media->update(['is_primary' => false]);
        }
    }

    private function findMediaForLegacyImage(ProductImage $image): ?ProductMedia
    {
        if (! $image->path) {
            return null;
        }

        $url = Storage::disk('public')->url($image->path);

        return ProductMedia::query()
            ->where('product_id', $image->product_id)
            ->whereNull('product_variant_id')
            ->where('type', ProductMediaType::Image)
            ->where('url', $url)
            ->first();
    }

    private function findLegacyImageForMedia(ProductMedia $media): ?ProductImage
    {
        if (! $media->url || $media->product_variant_id !== null) {
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
