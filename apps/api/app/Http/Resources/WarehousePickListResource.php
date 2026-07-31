<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WarehousePickList */
class WarehousePickListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return [
            'id' => $this->id,
            'warehouse_job_id' => $this->warehouse_job_id,
            'order_id' => $this->order_id,
            'picker_id' => $this->picker_id,
            'status' => $status,
            'status_label' => $this->status instanceof \App\Enums\WarehousePickListStatus
                ? $this->status->label()
                : null,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'picker' => $this->whenLoaded('picker', fn () => $this->picker ? [
                'id' => $this->picker->id,
                'name' => $this->picker->name,
                'email' => $this->picker->email,
            ] : null),
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'customer' => $this->order->relationLoaded('user') && $this->order->user ? [
                    'id' => $this->order->user->id,
                    'name' => $this->order->user->name,
                    'email' => $this->order->user->email,
                ] : null,
            ] : null),
            'warehouse_job' => $this->whenLoaded('warehouseJob', fn () => $this->warehouseJob ? [
                'id' => $this->warehouseJob->id,
                'job_number' => $this->warehouseJob->job_number,
                'status' => $this->warehouseJob->status instanceof \BackedEnum
                    ? $this->warehouseJob->status->value
                    : $this->warehouseJob->status,
            ] : null),
            'lines' => WarehousePickListLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
