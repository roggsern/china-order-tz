<?php

namespace App\Actions\AdminProductMedia;

use App\Enums\ProductMediaType;
use App\Http\Requests\Admin\StoreProductMediaRequest;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\ProductMedia\ProductImageWriteSyncService;
use App\Support\ProductMediaUrl;
use Illuminate\Support\Facades\DB;

class CreateProductMediaAction
{
    public function __construct(
        private readonly ProductImageWriteSyncService $imageWriteSync,
    ) {}

    public function handle(StoreProductMediaRequest $request, Product $product): ProductMedia
    {
        $validated = $request->validated();
        $type = ProductMediaType::from($validated['type']);

        if ($type === ProductMediaType::Image && $request->hasFile('file')) {
            return $this->imageWriteSync->storeUploadedImage(
                $request->file('file'),
                $product,
                [
                    'alt_text' => $validated['alt_text'] ?? null,
                    'title' => $validated['title'] ?? null,
                    'sort_order' => isset($validated['sort_order'])
                        ? (int) $validated['sort_order']
                        : (int) $product->media()->max('sort_order') + 1,
                    'is_primary' => (bool) ($validated['is_primary'] ?? false),
                    'is_active' => $validated['is_active'] ?? true,
                ],
            )->catalogMedia;
        }

        return DB::transaction(function () use ($validated, $type, $product) {
            $url = $validated['url'] ?? null;
            $thumbnail = $validated['thumbnail_url'] ?? null;

            if ($type === ProductMediaType::Video) {
                ProductMediaUrl::assertSupportedVideoUrl((string) $url);
                $thumbnail = $thumbnail ?? ProductMediaUrl::youtubeThumbnail((string) $url);
            }

            $sortOrder = (int) ($validated['sort_order'] ?? (
                (int) $product->media()->max('sort_order') + 1
            ));

            $isPrimary = (bool) ($validated['is_primary'] ?? false);
            if ($type === ProductMediaType::Image && ! $product->media()->images()->exists()) {
                $isPrimary = true;
            }

            if ($isPrimary && $type === ProductMediaType::Image) {
                $product->media()->images()->update(['is_primary' => false]);
            }

            if ($type === ProductMediaType::Video) {
                $isPrimary = false;
            }

            return ProductMedia::query()->create([
                'product_id' => $product->id,
                'type' => $type,
                'url' => $url,
                'thumbnail_url' => $thumbnail,
                'alt_text' => $validated['alt_text'] ?? null,
                'title' => $validated['title'] ?? null,
                'sort_order' => $sortOrder,
                'is_primary' => $isPrimary,
                'is_active' => $validated['is_active'] ?? true,
            ]);
        });
    }
}
