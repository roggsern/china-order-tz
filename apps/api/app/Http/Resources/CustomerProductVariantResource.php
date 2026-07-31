<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsCustomerCatalogPrice;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\Catalog\ProductVariantAttributeResolver;
use App\Services\Inventory\CatalogStockPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductVariant */
class CustomerProductVariantResource extends JsonResource
{
    use PresentsCustomerCatalogPrice;

    public function toArray(Request $request): array
    {
        $presenter = app(CatalogStockPresenter::class);
        $product = $this->relationLoaded('product') ? $this->product : null;
        $stock = $product !== null
            ? $presenter->resolveForProduct($product, $this->resource)
            : app(\App\Services\Inventory\StockResolver::class)->resolveVariantProduct($this->resource);
        $inventoryContract = $presenter->toInventoryContract($stock, includeWarehouseLocation: false);
        $available = max(0, $stock->quantityAvailable);
        $includeStock = $this->relationLoaded('inventory')
            || $this->relationLoaded('inventories')
            || $stock->resolved;

        $mediaResolver = app(CustomerProductMediaResolver::class);
        $attributeResolver = app(ProductVariantAttributeResolver::class);
        $includeMedia = $product !== null;
        $displayAttributes = $attributeResolver->resolve($this->resource);
        $resolvedPrice = $this->resolvedVariantDisplayPrice($this->resource, $product);

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'price' => $resolvedPrice,
            'compare_at_price' => $this->compare_at_price,
            'weight' => $this->weight,
            'effective_price' => $this->when(
                $this->relationLoaded('product') || $product !== null,
                fn () => $resolvedPrice,
            ),
            'primary_image' => $this->when(
                $includeMedia,
                fn () => $mediaResolver->resolvePrimary($product, $this->resource),
            ),
            'images' => $this->when(
                $includeMedia,
                fn () => $mediaResolver->resolveGallery($product, $this->resource),
            ),
            'display_attributes' => $displayAttributes,
            'attribute_values' => $this->resolveCustomerAttributeValues($request, $displayAttributes),
            'inventory' => $this->when($includeStock, fn () => $inventoryContract),
            'stock' => $this->when($includeStock, fn () => $available),
            'in_stock' => $this->when($includeStock, fn () => $available > 0),
        ];
    }

    /**
     * Catalog-first attribute payload kept compatible with cart/PDP mappers.
     *
     * @param  list<array{attribute: string, value: string}>  $displayAttributes
     * @return list<array<string, mixed>>
     */
    private function resolveCustomerAttributeValues(Request $request, array $displayAttributes): array
    {
        $this->resource->loadMissing([
            'catalogAttributeValues.attribute',
            'catalogAttributeValues.option',
            'attributeValues.attribute',
        ]);

        if ($this->catalogAttributeValues->isNotEmpty()) {
            return $this->catalogAttributeValues
                ->map(function ($row) {
                    $attribute = $row->relationLoaded('attribute') ? $row->attribute : null;
                    $option = $row->relationLoaded('option') ? $row->option : null;

                    return [
                        'id' => $row->option_id ?? $row->id,
                        'product_attribute_id' => $row->catalog_attribute_id,
                        'value' => $option?->value ?? $row->value_text,
                        'slug' => $option?->slug,
                        'color_code' => null,
                        'sort_order' => null,
                        'attribute' => $attribute === null ? null : [
                            'id' => $attribute->id,
                            'name' => $attribute->name,
                            'slug' => $attribute->slug,
                        ],
                    ];
                })
                ->values()
                ->all();
        }

        if ($this->attributeValues->isNotEmpty()) {
            return collect(
                ProductAttributeValueResource::collection($this->attributeValues)->resolve($request),
            )->values()->all();
        }

        // Fallback: synthesize nested rows from normalized display attributes.
        return collect($displayAttributes)
            ->map(fn (array $row) => [
                'id' => null,
                'product_attribute_id' => null,
                'value' => $row['value'],
                'slug' => null,
                'color_code' => null,
                'sort_order' => null,
                'attribute' => [
                    'id' => null,
                    'name' => $row['attribute'],
                    'slug' => null,
                ],
            ])
            ->values()
            ->all();
    }
}
