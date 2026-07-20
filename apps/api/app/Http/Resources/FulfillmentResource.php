<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Fulfillment */
class FulfillmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'strategy' => $this->strategy?->value ?? $this->strategy,
            'strategy_label' => $this->strategy?->label(),
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status?->label(),
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ] : null),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'notes' => $this->notes,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->status?->value ?? $this->order->status,
                'total' => $this->order->total,
                'currency' => $this->order->currency,
                'paid_at' => $this->order->paid_at,
                'customer' => $this->order->relationLoaded('user') && $this->order->user
                    ? [
                        'id' => $this->order->user->id,
                        'name' => $this->order->user->name,
                        'email' => $this->order->user->email,
                        'phone' => $this->order->user->phone ?? null,
                    ]
                    : null,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
