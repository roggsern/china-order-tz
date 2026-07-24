<?php

namespace App\Actions\AdminProductMedia;

use App\Enums\ProductMediaType;
use App\Http\Requests\Admin\UpdateProductMediaRequest;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\ProductMedia\ProductPrimarySyncService;
use App\Support\ProductMediaUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateProductMediaAction
{
    public function __construct(
        private readonly ProductPrimarySyncService $primarySync,
    ) {}

    public function handle(
        UpdateProductMediaRequest $request,
        Product $product,
        ProductMedia $media,
    ): ProductMedia {
        if ($media->product_id !== $product->id) {
            abort(404);
        }

        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $request, $media) {
            if ($request->hasFile('file') && $media->type === ProductMediaType::Image) {
                throw ValidationException::withMessages([
                    'file' => ['Image replacement is not supported. Delete the image and upload a new one.'],
                ]);
            }

            $data = [];

            foreach (['alt_text', 'title', 'sort_order', 'is_active', 'thumbnail_url'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $data[$field] = $validated[$field];
                }
            }

            if (array_key_exists('type', $validated)) {
                $data['type'] = ProductMediaType::from($validated['type']);
            }

            $type = $data['type'] ?? $media->type;

            if (array_key_exists('url', $validated) && filled($validated['url'])) {
                $data['url'] = $validated['url'];
                if ($type === ProductMediaType::Video || ($data['type'] ?? null) === ProductMediaType::Video) {
                    ProductMediaUrl::assertSupportedVideoUrl((string) $validated['url']);
                    $data['thumbnail_url'] = $data['thumbnail_url']
                        ?? ProductMediaUrl::youtubeThumbnail((string) $validated['url']);
                }
            }

            if ($data !== []) {
                $media->update($data);
                $media = $media->fresh();
            }

            if (array_key_exists('is_primary', $validated)) {
                if ($validated['is_primary']) {
                    if ($media->type === ProductMediaType::Video) {
                        throw ValidationException::withMessages([
                            'is_primary' => ['Only image media can be primary.'],
                        ]);
                    }

                    return $this->primarySync->setPrimaryFromCatalogMedia($media);
                }

                return $this->primarySync->clearPrimaryFromCatalogMedia($media);
            }

            return $media;
        });
    }
}
