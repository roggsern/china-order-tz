<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CartItem */
class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $available = null;

        if ($this->relationLoaded('variant') && $this->variant?->relationLoaded('inventories')) {
            $main = $this->variant->inventories->firstWhere('warehouse_code', 'MAIN')
                ?? $this->variant->inventories->first();
            $available = $main?->available();
        }

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'price_snapshot' => $this->price_snapshot ?? $this->unit_price,
            'currency' => $this->currency ?? 'TZS',
            'available_stock' => $available,
            'subtotal' => $this->subtotal(),
            'product' => new ProductResource($this->whenLoaded('product')),
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
        ];
    }
}
