<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderConfirmationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order' => [
                'id' => $this->id,
                'order_number' => $this->order_number,
                'status' => $this->status->value,
                'source' => $this->resolveSource(),
                'placed_at' => $this->placed_at,
            ],
            'items' => $this->items->map(function ($item) {
                $data = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name_snapshot ?? $item->product_name,
                    'product_name_snapshot' => $item->product_name_snapshot,
                    'variant_name_snapshot' => $item->variant_name_snapshot,
                    'sku_snapshot' => $item->sku_snapshot,
                    'unit_price_snapshot' => $item->unit_price_snapshot ?? $item->unit_price,
                    'shipping_mode_snapshot' => $item->shipping_mode_snapshot,
                    'shipping_price_snapshot' => $item->shipping_price_snapshot,
                    'product_image_snapshot' => $item->product_image_snapshot ?? $item->image_snapshot,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price_snapshot ?? $item->unit_price,
                    'subtotal' => $item->line_total ?? $item->total_price,
                ];

                if ($item->delivery_status !== null) {
                    $data['delivery_status'] = $item->delivery_status;
                } else {
                    $data['shipping_method'] = $item->shipping_mode_snapshot ?? $item->shipping_method;
                    $data['shipping_price'] = $item->shipping_price_snapshot ?? $item->shipping_price;
                    $data['shipping_subtotal'] = $item->shipping_subtotal;
                }

                return $data;
            })->values()->all(),
            'summary' => [
                'subtotal' => $this->subtotal,
                'shipping' => $this->shipping_amount,
                'discount' => $this->discount_amount,
                'total' => $this->total,
            ],
        ];
    }
}
