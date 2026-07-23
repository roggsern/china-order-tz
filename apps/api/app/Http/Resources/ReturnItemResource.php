<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReturnItem */
class ReturnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_request_id' => $this->return_request_id,
            'order_item_id' => $this->order_item_id,
            'quantity' => $this->quantity,
            'reason' => $this->reason,
            'condition' => $this->condition,
            'resolution' => $this->resolution instanceof \BackedEnum
                ? $this->resolution->value
                : $this->resolution,
            // Admin operational field only — never expose to customer return payloads (RC1-G1 / G3).
            'inventory_disposition' => $this->when(
                $request->is('api/v1/admin/*'),
                fn () => $this->inventory_disposition instanceof \BackedEnum
                    ? $this->inventory_disposition->value
                    : $this->inventory_disposition,
            ),
            'refund_amount' => $this->refund_amount,
            'replacement_requested' => (bool) $this->replacement_requested,
            'order_item' => $this->whenLoaded('orderItem', fn () => $this->orderItem ? [
                'id' => $this->orderItem->id,
                'product_name' => $this->orderItem->product_name_snapshot
                    ?? $this->orderItem->product_name,
                'quantity' => $this->orderItem->quantity,
                'unit_price' => $this->orderItem->unit_price_snapshot
                    ?? $this->orderItem->unit_price,
            ] : null),
        ];
    }
}
