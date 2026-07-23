<?php

namespace App\Actions\AdminProductMedia;

use App\Enums\ProductMediaType;
use App\Http\Requests\Admin\UpdateProductMediaRequest;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Support\ProductMediaUrl;
use App\Support\Security\SecureImageUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UpdateProductMediaAction
{
    public function handle(
        UpdateProductMediaRequest $request,
        Product $product,
        ProductMedia $media,
    ): ProductMedia {
        if ($media->product_id !== $product->id) {
            abort(404);
        }

        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $request, $product, $media) {
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

            if ($request->hasFile('file')) {
                $path = SecureImageUpload::storePublic($request->file('file'), 'product-media');
                $data['url'] = Storage::disk('public')->url($path);
                $data['type'] = ProductMediaType::Image;
                $data['thumbnail_url'] = $data['thumbnail_url'] ?? $data['url'];
                $type = ProductMediaType::Image;
            } elseif (array_key_exists('url', $validated) && filled($validated['url'])) {
                $data['url'] = $validated['url'];
                if ($type === ProductMediaType::Video || ($data['type'] ?? null) === ProductMediaType::Video) {
                    ProductMediaUrl::assertSupportedVideoUrl((string) $validated['url']);
                    $data['thumbnail_url'] = $data['thumbnail_url']
                        ?? ProductMediaUrl::youtubeThumbnail((string) $validated['url']);
                }
            }

            if (array_key_exists('is_primary', $validated) && $validated['is_primary']) {
                if ($type === ProductMediaType::Video) {
                    throw ValidationException::withMessages([
                        'is_primary' => ['Only image media can be primary.'],
                    ]);
                }

                $product->media()->images()->where('id', '!=', $media->id)->update(['is_primary' => false]);
                $data['is_primary'] = true;
            } elseif (array_key_exists('is_primary', $validated)) {
                $data['is_primary'] = (bool) $validated['is_primary'];
            }

            if ($data !== []) {
                $media->update($data);
            }

            return $media->fresh();
        });
    }
}
