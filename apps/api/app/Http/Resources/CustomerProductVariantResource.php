<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductVariant */
class CustomerProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'inventory' => new InventoryResource($this->whenLoaded('inventory')),
            'stock' => $this->when(
                $this->relationLoaded('inventory'),
                fn () => max(0, (int) ($this->inventory?->availableQuantity() ?? 0)),
            ),
            'in_stock' => $this->when(
                $this->relationLoaded('inventory'),
                fn () => max(0, (int) ($this->inventory?->availableQuantity() ?? 0)) > 0,
            ),
        ];
    }
}
