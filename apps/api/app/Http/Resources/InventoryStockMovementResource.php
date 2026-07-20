<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InventoryStockMovement */
class InventoryStockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movement_type' => $this->movement_type instanceof \BackedEnum
                ? $this->movement_type->value
                : $this->movement_type,
            'product_variant_id' => $this->product_variant_id,
            'sku' => $this->whenLoaded('variant', fn () => $this->variant?->sku),
            'product_name' => $this->whenLoaded('variant', fn () => $this->variant?->product?->name),
            'store_id' => $this->store_id,
            'store_name' => $this->whenLoaded('store', fn () => $this->store?->name),
            'inventory_location_id' => $this->inventory_location_id,
            'quantity_before' => (int) $this->quantity_before,
            'quantity_change' => (int) $this->quantity_change,
            'quantity_after' => (int) $this->quantity_after,
            'damaged_after' => (int) $this->damaged_after,
            'inspection_after' => (int) $this->inspection_after,
            'reason' => $this->reason,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'created_at' => $this->created_at,
        ];
    }
}
