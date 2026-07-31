<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WarehousePickListLine */
class WarehousePickListLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return [
            'id' => $this->id,
            'pick_list_id' => $this->pick_list_id,
            'order_item_id' => $this->order_item_id,
            'product_variant_id' => $this->product_variant_id,
            'product_name' => $this->product_name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'picked_quantity' => $this->picked_quantity,
            'warehouse_bin_id' => $this->warehouse_bin_id,
            'status' => $status,
            'status_label' => $this->status instanceof \App\Enums\WarehousePickListLineStatus
                ? $this->status->label()
                : null,
            'warehouse_bin' => $this->whenLoaded('warehouseBin', fn () => $this->warehouseBin ? [
                'id' => $this->warehouseBin->id,
                'code' => $this->warehouseBin->code,
                'name' => $this->warehouseBin->name,
                'zone' => $this->warehouseBin->relationLoaded('zone') && $this->warehouseBin->zone ? [
                    'code' => $this->warehouseBin->zone->code,
                    'name' => $this->warehouseBin->zone->name,
                ] : null,
            ] : null),
        ];
    }
}
