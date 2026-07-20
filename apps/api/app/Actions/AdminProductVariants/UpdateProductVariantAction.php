<?php

namespace App\Actions\AdminProductVariants;

use App\Actions\AdminProductVariants\Concerns\ResolvesVariantDefaults;
use App\Http\Requests\Admin\UpdateProductVariantRequest;
use App\Http\Resources\AdminCatalogProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\SyncVariantCatalogAttributeValues;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateProductVariantAction
{
    use ResolvesVariantDefaults;

    public function __construct(
        private readonly SyncVariantCatalogAttributeValues $syncAttributeValues,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        UpdateProductVariantRequest $request,
        Product $product,
        ProductVariant $variant,
    ): array {
        if ($variant->product_id !== $product->id) {
            throw ValidationException::withMessages([
                'variant' => ['Variant does not belong to this product.'],
            ]);
        }

        $data = $request->validated();

        $variant = DB::transaction(function () use ($product, $variant, $data) {
            if (array_key_exists('is_default', $data) && $data['is_default']) {
                $this->clearOtherDefaults($product, $variant->id);
            }

            $variant->fill([
                'name' => array_key_exists('name', $data) ? $data['name'] : $variant->name,
                'sku' => array_key_exists('sku', $data) ? $data['sku'] : $variant->sku,
                'barcode' => array_key_exists('barcode', $data) ? $data['barcode'] : $variant->barcode,
                'price' => array_key_exists('price', $data) ? $data['price'] : $variant->price,
                'sort_order' => array_key_exists('sort_order', $data)
                    ? (int) $data['sort_order']
                    : $variant->sort_order,
            ]);

            if (array_key_exists('status', $data) || array_key_exists('is_active', $data)) {
                $variant->is_active = $this->resolveIsActive(
                    $data['status'] ?? null,
                    $data['is_active'] ?? null,
                    (bool) $variant->is_active,
                );
            }

            if (array_key_exists('is_default', $data)) {
                $variant->is_default = (bool) $data['is_default'];
            }

            $variant->save();

            if (array_key_exists('attribute_values', $data)) {
                $product->loadMissing(['catalogProductType.attributes.options']);
                $allowed = $product->catalogProductType?->attributes?->keyBy('id');
                $this->syncAttributeValues->handle($variant, $data['attribute_values'] ?? [], $allowed);
            }

            $this->ensureSingleDefault($product);

            return $variant->fresh(['catalogAttributeValues.attribute', 'catalogAttributeValues.option']);
        });

        return (new AdminCatalogProductVariantResource($variant))->resolve();
    }
}
