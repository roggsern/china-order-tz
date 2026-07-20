<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WarehouseJob */
class WarehouseJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status;

        return [
            'id' => $this->id,
            'job_number' => $this->job_number,
            'order_id' => $this->order_id,
            'fulfillment_id' => $this->fulfillment_id,
            'status' => $status instanceof \BackedEnum ? $status->value : (string) $status,
            'status_label' => $status instanceof \App\Enums\WarehouseJobStatus
                ? $status->label()
                : null,
            'next_status' => $status instanceof \App\Enums\WarehouseJobStatus
                ? $status->nextForward()?->value
                : null,
            'picker_id' => $this->picker_id,
            'packer_id' => $this->packer_id,
            'picker' => $this->whenLoaded('picker', fn () => $this->picker ? [
                'id' => $this->picker->id,
                'name' => $this->picker->name,
                'email' => $this->picker->email,
            ] : null),
            'packer' => $this->whenLoaded('packer', fn () => $this->packer ? [
                'id' => $this->packer->id,
                'name' => $this->packer->name,
                'email' => $this->packer->email,
            ] : null),
            'picked_at' => $this->picked_at,
            'packed_at' => $this->packed_at,
            'ready_at' => $this->ready_at,
            'notes' => $this->notes,
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->status?->value ?? $this->order->status,
                'customer' => $this->order->relationLoaded('user') && $this->order->user
                    ? [
                        'id' => $this->order->user->id,
                        'name' => trim(($this->order->user->first_name ?? '').' '.($this->order->user->last_name ?? ''))
                            ?: ($this->order->user->name ?? $this->order->user->email),
                        'email' => $this->order->user->email,
                    ]
                    : null,
            ] : null),
            'fulfillment' => $this->whenLoaded('fulfillment', fn () => $this->fulfillment ? [
                'id' => $this->fulfillment->id,
                'status' => $this->fulfillment->status?->value ?? $this->fulfillment->status,
                'strategy' => $this->fulfillment->strategy?->value ?? $this->fulfillment->strategy,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
