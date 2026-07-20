<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ConfigurationPriceTier */
class ConfigurationPriceTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'configuration_id' => $this->product_variant_id,
            'min_quantity' => $this->min_quantity,
            'tier_type' => $this->tier_type?->value ?? 'fixed_unit',
            'unit_price' => $this->unit_price,
            'discount_percent' => $this->discount_percent,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
