<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReceivingRecord */
class ReceivingRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'store_id' => $this->store_id,
            'inventory_location_id' => $this->inventory_location_id,
            'store' => $this->whenLoaded('store', fn () => [
                'id' => $this->store?->id,
                'code' => $this->store?->code,
                'name' => $this->store?->name,
            ]),
            'received_by' => $this->received_by,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'received_at' => $this->received_at,
            'notes' => $this->notes,
            'received_by_admin' => $this->whenLoaded('receivedByAdmin', fn () => [
                'id' => $this->receivedByAdmin?->id,
                'name' => $this->receivedByAdmin?->name,
                'email' => $this->receivedByAdmin?->email,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'purchase_order_item_id' => $item->purchase_order_item_id,
                'quantity_received' => (int) $item->quantity_received,
                'purchase_order_item' => $item->relationLoaded('purchaseOrderItem')
                    ? new PurchaseOrderItemResource($item->purchaseOrderItem)
                    : null,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
