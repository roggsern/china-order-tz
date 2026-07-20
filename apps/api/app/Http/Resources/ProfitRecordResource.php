<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProfitRecord */
class ProfitRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'revenue' => $this->revenue,
            'total_cost' => $this->total_cost,
            'gross_profit' => $this->gross_profit,
            'margin_percentage' => $this->margin_percentage,
            'currency' => $this->currency,
            'calculated_at' => $this->calculated_at,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order?->id,
                'order_number' => $this->order?->order_number,
                'status' => $this->order?->status instanceof \BackedEnum
                    ? $this->order->status->value
                    : $this->order?->status,
                'commerce_channel_id' => $this->order?->commerce_channel_id,
                'commerce_channel_snapshot' => $this->order?->commerce_channel_snapshot,
                'placed_at' => $this->order?->placed_at,
                'total' => $this->order?->total,
                'currency' => $this->order?->currency,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
