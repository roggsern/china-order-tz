<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WarehousePackingRecord */
class WarehousePackingRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return [
            'id' => $this->id,
            'warehouse_job_id' => $this->warehouse_job_id,
            'packer_id' => $this->packer_id,
            'status' => $status,
            'status_label' => $this->status instanceof \App\Enums\WarehousePackingStatus
                ? $this->status->label()
                : null,
            'package_status' => $this->package_status,
            'notes' => $this->notes,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'packer' => $this->whenLoaded('packer', fn () => $this->packer ? [
                'id' => $this->packer->id,
                'name' => $this->packer->name,
            ] : null),
            'warehouse_job' => $this->whenLoaded('warehouseJob', fn () => $this->warehouseJob ? [
                'id' => $this->warehouseJob->id,
                'job_number' => $this->warehouseJob->job_number,
                'order' => $this->warehouseJob->relationLoaded('order') && $this->warehouseJob->order ? [
                    'order_number' => $this->warehouseJob->order->order_number,
                ] : null,
            ] : null),
            'lines' => WarehousePackingLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
