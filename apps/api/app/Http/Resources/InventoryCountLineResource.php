<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InventoryCountLine */
class InventoryCountLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'sku' => $this->whenLoaded('variant', fn () => $this->variant?->sku),
            'product_name' => $this->whenLoaded('variant', fn () => $this->variant?->product?->name),
            'system_quantity' => (int) $this->system_quantity,
            'counted_quantity' => $this->counted_quantity,
            'difference' => $this->difference,
            'reason' => $this->reason,
            'is_adjusted' => (bool) $this->is_adjusted,
        ];
    }
}
