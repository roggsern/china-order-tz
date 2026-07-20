<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PurchaseOrderItem */
class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity_ordered' => (int) $this->quantity_ordered,
            'quantity_received' => (int) $this->quantity_received,
            'quantity_outstanding' => $this->quantityOutstanding(),
            'unit_cost' => $this->unit_cost,
            'currency' => $this->currency,
            'variant' => $this->whenLoaded('variant', fn () => [
                'id' => $this->variant?->id,
                'sku' => $this->variant?->sku,
                'name' => $this->variant?->name,
                'product' => $this->variant?->relationLoaded('product') ? [
                    'id' => $this->variant->product?->id,
                    'name' => $this->variant->product?->name,
                ] : null,
            ]),
        ];
    }
}
