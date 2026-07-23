<?php

namespace App\Http\Resources;

use App\Services\Inventory\CatalogStockPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductVariant */
class CustomerProductVariantResource extends JsonResource
{
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

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'weight' => $this->weight,
            'effective_price' => $this->when(
                $this->relationLoaded('product'),
                fn () => $this->effectivePrice(),
            ),
            'attribute_values' => ProductAttributeValueResource::collection($this->whenLoaded('attributeValues')),
            'inventory' => $this->when($includeStock, fn () => $inventoryContract),
            'stock' => $this->when($includeStock, fn () => $available),
            'in_stock' => $this->when($includeStock, fn () => $available > 0),
        ];
    }
}
