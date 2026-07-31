<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WarehouseStockTransferLine */
class WarehouseStockTransferLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_id' => $this->transfer_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'product_variant' => $this->whenLoaded('productVariant', fn () => $this->productVariant ? [
                'id' => $this->productVariant->id,
                'sku' => $this->productVariant->sku,
                'name' => $this->productVariant->name ?? null,
            ] : null),
        ];
    }
}
