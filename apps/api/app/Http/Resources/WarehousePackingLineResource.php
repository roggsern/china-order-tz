<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WarehousePackingLine */
class WarehousePackingLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'packing_record_id' => $this->packing_record_id,
            'order_item_id' => $this->order_item_id,
            'quantity' => $this->quantity,
            'packed_quantity' => $this->packed_quantity,
        ];
    }
}
