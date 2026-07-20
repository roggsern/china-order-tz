<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PromotionUsage */
class PromotionUsageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'promotion_id' => $this->promotion_id,
            'customer_id' => $this->customer_id,
            'order_id' => $this->order_id,
            'discount_amount' => $this->discount_amount,
            'currency' => $this->currency,
            'used_at' => $this->used_at,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
                'email' => $this->customer?->email,
            ]),
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order?->id,
                'order_number' => $this->order?->order_number,
                'total' => $this->order?->total,
                'status' => $this->order?->status instanceof \BackedEnum
                    ? $this->order->status->value
                    : $this->order?->status,
            ]),
        ];
    }
}
