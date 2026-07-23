<?php

namespace App\Http\Resources;

use App\Services\Inventory\CatalogStockPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductVariant */
class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $presenter = app(CatalogStockPresenter::class);
        $product = $this->relationLoaded('product') ? $this->product : null;
        $includeInventory = $this->relationLoaded('inventory') || $this->relationLoaded('inventories');

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'barcode' => $this->barcode,
            'weight' => $this->weight,
            'is_active' => $this->is_active,
            'effective_price' => $this->when(
                $this->relationLoaded('product'),
                fn () => $this->effectivePrice()
            ),
            'attribute_values' => ProductAttributeValueResource::collection($this->whenLoaded('attributeValues')),
            'inventory' => $this->when(
                $includeInventory,
                fn () => $presenter->variantInventoryContract($this->resource, $product),
            ),
            'price_tiers' => ConfigurationPriceTierResource::collection($this->whenLoaded('priceTiers')),
        ];
    }
}
