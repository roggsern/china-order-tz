<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class CustomerOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof OrderStatus
            ? $this->status
            : OrderStatus::tryFrom((string) $this->status);

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'source' => $this->resolveSource(),
            'status' => $status?->value ?? (string) $this->status,
            'status_label' => $status?->customerLabel() ?? 'Status unavailable',
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'grand_total' => $this->grand_total,
            'total' => $this->grand_total,
            'created_at' => $this->created_at,
        ];
    }
}
