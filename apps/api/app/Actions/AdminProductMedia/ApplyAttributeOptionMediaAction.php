<?php

namespace App\Actions\AdminProductMedia;

use App\Enums\ProductMediaType;
use App\Http\Requests\Admin\ApplyAttributeOptionMediaRequest;
use App\Models\CatalogAttributeOption;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Support\Security\SecureImageUpload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ApplyAttributeOptionMediaAction
{
    /**
     * Upload (or accept) one image and attach it to every active variant that
     * uses the given catalog attribute option. Variants that already have
     * images are skipped so per-variant overrides stay intact. One storage
     * object is reused via identical product_media.url rows.
     *
     * @return array{
     *     catalog_attribute_option_id: string,
     *     option_value: string,
     *     attribute_name: string|null,
     *     url: string,
     *     matched_variant_count: int,
     *     applied_count: int,
     *     skipped_count: int,
     *     skipped_variant_ids: list<string>,
     *     media: Collection<int, ProductMedia>
     * }
     */
    public function handle(ApplyAttributeOptionMediaRequest $request, Product $product): array
    {
        $validated = $request->validated();
        $optionId = (string) $validated['catalog_attribute_option_id'];

        $option = CatalogAttributeOption::query()
            ->with('attribute:id,name,slug')
            ->findOrFail($optionId);

        $variants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->whereHas(
                'catalogAttributeValues',
                fn ($query) => $query->where('option_id', $optionId),
            )
            ->orderBy('sort_order')
            ->get();

        if ($variants->isEmpty()) {
            throw ValidationException::withMessages([
                'catalog_attribute_option_id' => [
                    'No active variants on this product use the selected attribute option.',
                ],
            ]);
        }

        $url = $this->resolveImageUrl($request, $validated);
        $altText = $validated['alt_text'] ?? $option->value;
        $title = $validated['title'] ?? ($option->value.' image');

        return DB::transaction(function () use (
            $product,
            $option,
            $variants,
            $url,
            $altText,
            $title,
        ) {
            $created = collect();
            $skippedIds = [];

            foreach ($variants as $variant) {
                $existingImages = ProductMedia::query()
                    ->where('product_id', $product->id)
                    ->where('product_variant_id', $variant->id)
                    ->images()
                    ->get(['id', 'url']);

                if ($existingImages->isNotEmpty()) {
                    $skippedIds[] = $variant->id;

                    continue;
                }

                $sortOrder = (int) ProductMedia::query()
                    ->where('product_id', $product->id)
                    ->where('product_variant_id', $variant->id)
                    ->max('sort_order') + 1;

                $created->push(
                    ProductMedia::query()->create([
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'type' => ProductMediaType::Image,
                        'url' => $url,
                        'thumbnail_url' => $url,
                        'alt_text' => $altText,
                        'title' => $title,
                        'sort_order' => $sortOrder,
                        'is_primary' => true,
                        'is_active' => true,
                    ])->load('variant'),
                );
            }

            return [
                'catalog_attribute_option_id' => $option->id,
                'option_value' => $option->value,
                'attribute_name' => $option->attribute?->name,
                'url' => $url,
                'matched_variant_count' => $variants->count(),
                'applied_count' => $created->count(),
                'skipped_count' => count($skippedIds),
                'skipped_variant_ids' => $skippedIds,
                'media' => $created,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveImageUrl(ApplyAttributeOptionMediaRequest $request, array $validated): string
    {
        if ($request->hasFile('file')) {
            $path = SecureImageUpload::storePublic($request->file('file'), 'products');

            return Storage::disk('public')->url($path);
        }

        return (string) $validated['url'];
    }
}
