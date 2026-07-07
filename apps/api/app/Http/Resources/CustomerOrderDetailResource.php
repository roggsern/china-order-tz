<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class CustomerOrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payment = $this->payments->sortByDesc('created_at')->first();

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'source' => $this->resolveSource(),
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'items' => $this->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->total_price,
            ])->values()->all(),
            'summary' => [
                'subtotal' => $this->subtotal,
                'shipping' => $this->shipping_amount,
                'discount' => $this->discount_amount,
                'total' => $this->total,
            ],
            'payment' => [
                'payment_status' => $payment?->status?->value,
                'payment_method' => $payment?->method?->value,
            ],
            'shipment' => [
                'status' => 'Preparing',
            ],
        ];
    }
}
