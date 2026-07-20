<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PurchaseOrder */
class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'purchase_number' => $this->purchase_number,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'ordered_at' => $this->ordered_at,
            'confirmed_at' => $this->confirmed_at,
            'completed_at' => $this->completed_at,
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
                'code' => $this->supplier?->code,
                'country' => $this->supplier?->country,
            ]),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'receiving_records' => ReceivingRecordResource::collection($this->whenLoaded('receivingRecords')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
