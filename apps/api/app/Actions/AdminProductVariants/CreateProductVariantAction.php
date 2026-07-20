<?php

namespace App\Actions\AdminProductVariants;

use App\Actions\AdminProductVariants\Concerns\ResolvesVariantDefaults;
use App\Http\Requests\Admin\StoreProductVariantRequest;
use App\Http\Resources\AdminCatalogProductVariantResource;
use App\Models\CatalogAttributeOption;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\GenerateVariantSku;
use App\Services\Catalog\SyncVariantCatalogAttributeValues;
use Illuminate\Support\Facades\DB;

class CreateProductVariantAction
{
    use ResolvesVariantDefaults;

    public function __construct(
        private readonly SyncVariantCatalogAttributeValues $syncAttributeValues,
        private readonly GenerateVariantSku $generateVariantSku,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(StoreProductVariantRequest $request, Product $product): array
    {
        $data = $request->validated();
        $attributeRows = $data['attribute_values'] ?? [];
        $labels = $this->resolveLabels($attributeRows);

        $variant = DB::transaction(function () use ($product, $data, $attributeRows, $labels) {
            $isDefault = (bool) ($data['is_default'] ?? false);
            if ($isDefault) {
                $this->clearOtherDefaults($product);
            }

            $sku = trim((string) ($data['sku'] ?? ''));
            if ($sku === '') {
                $sku = $this->generateVariantSku->handle($product, $labels);
            }

            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '' && $labels !== []) {
                $name = implode(' ', $labels);
            }

            $sortOrder = (int) ($data['sort_order'] ?? (
                (int) ProductVariant::query()->where('product_id', $product->id)->max('sort_order') + 1
            ));

            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'name' => $name !== '' ? $name : null,
                'sku' => $sku,
                'barcode' => $data['barcode'] ?? null,
                'price' => $data['price'] ?? null,
                'is_active' => $this->resolveIsActive($data['status'] ?? null, $data['is_active'] ?? null),
                'is_default' => $isDefault,
                'sort_order' => $sortOrder,
            ]);

            if ($attributeRows !== []) {
                $product->loadMissing(['catalogProductType.attributes.options']);
                $allowed = $product->catalogProductType?->attributes?->keyBy('id');
                $this->syncAttributeValues->handle($variant, $attributeRows, $allowed);
            }

            if (! $isDefault) {
                $this->ensureSingleDefault($product);
            }

            return $variant->fresh(['catalogAttributeValues.attribute', 'catalogAttributeValues.option']);
        });

        return (new AdminCatalogProductVariantResource($variant))->resolve();
    }

    /**
     * @param  list<array<string, mixed>>  $attributeRows
     * @return list<string>
     */
    private function resolveLabels(array $attributeRows): array
    {
        $labels = [];

        foreach ($attributeRows as $row) {
            if (! empty($row['value_text']) && is_string($row['value_text'])) {
                $labels[] = $row['value_text'];
                continue;
            }

            $optionId = $row['option_id'] ?? null;
            if (is_string($optionId) && $optionId !== '') {
                $option = CatalogAttributeOption::query()->find($optionId);
                if ($option !== null) {
                    $labels[] = $option->value;
                }
            }
        }

        return $labels;
    }
}
