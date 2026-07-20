<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SupplierProduct */
class SupplierProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'product_variant_id' => $this->product_variant_id,
            'supplier_sku' => $this->supplier_sku,
            'purchase_cost' => $this->purchase_cost,
            'currency' => $this->currency,
            'lead_time_days' => $this->lead_time_days,
            'is_active' => (bool) $this->is_active,
            'variant' => $this->whenLoaded('variant', fn () => [
                'id' => $this->variant?->id,
                'sku' => $this->variant?->sku,
                'name' => $this->variant?->name,
                'product' => $this->variant?->relationLoaded('product') ? [
                    'id' => $this->variant->product?->id,
                    'name' => $this->variant->product?->name,
                    'sku' => $this->variant->product?->sku,
                ] : null,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
