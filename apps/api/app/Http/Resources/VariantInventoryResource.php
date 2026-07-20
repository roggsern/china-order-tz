<?php

namespace App\Http\Resources;

use App\Models\VariantInventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VariantInventory */
class VariantInventoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'inventory_location_id' => $this->inventory_location_id,
            'warehouse_code' => $this->warehouse_code,
            'on_hand' => (int) $this->on_hand,
            'reserved' => (int) $this->reserved,
            'damaged' => (int) $this->damaged,
            'inspection' => (int) $this->inspection,
            'available' => $this->available(),
            'physical_quantity' => $this->physicalQuantity(),
            'reorder_level' => (int) $this->reorder_level,
            'safety_stock' => (int) $this->safety_stock,
            'needs_reorder' => $this->needsReorder(),
            'is_active' => (bool) $this->is_active,
            'sku' => $this->whenLoaded('variant', fn () => $this->variant?->sku),
            'product_name' => $this->whenLoaded('variant', fn () => $this->variant?->product?->name),
            'store_name' => $this->whenLoaded('inventoryLocation', fn () => $this->inventoryLocation?->store?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
