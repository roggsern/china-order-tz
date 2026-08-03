<?php

namespace App\Http\Resources;

use App\Models\ChinaCommercialStock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChinaCommercialStock */
class ChinaCommercialStockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'available_quantity' => (int) $this->available_quantity,
            'reserved_quantity' => (int) $this->reserved_quantity,
            'ordered_quantity' => (int) $this->ordered_quantity,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
