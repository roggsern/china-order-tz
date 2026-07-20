<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class CustomerOrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payment = $this->relationLoaded('payments')
            ? $this->payments->sortByDesc('created_at')->first()
            : null;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'source' => $this->resolveSource(),
            'status' => $this->status instanceof \App\Enums\OrderStatus
                ? $this->status->value
                : (string) $this->status,
            'status_label' => $this->status instanceof \App\Enums\OrderStatus
                ? $this->status->customerLabel()
                : 'Status unavailable',
            'created_at' => $this->created_at,
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'product_name' => $item->product_name_snapshot ?? $item->product_name,
                'product_name_snapshot' => $item->product_name_snapshot,
                'product_slug_snapshot' => $item->product_slug_snapshot,
                'brand_name_snapshot' => $item->brand_name_snapshot,
                'variant_name_snapshot' => $item->variant_name_snapshot,
                'variant_sku_snapshot' => $item->variant_sku_snapshot,
                'sku_snapshot' => $item->sku_snapshot,
                'currency_snapshot' => $item->currency_snapshot ?? $item->currency,
                'unit_price_snapshot' => $item->unit_price_snapshot ?? $item->unit_price,
                'shipping_mode_snapshot' => $item->shipping_mode_snapshot,
                'shipping_price_snapshot' => $item->shipping_price_snapshot,
                'shipping_notes_snapshot' => $item->shipping_notes_snapshot,
                'attributes_snapshot' => $item->attributes_snapshot,
                'product_image_snapshot' => $item->product_image_snapshot ?? $item->image_snapshot,
                'image_snapshot' => $item->product_image_snapshot ?? $item->image_snapshot,
                'quantity' => $item->quantity,
                'unit_price' => number_format((float) ($item->unit_price_snapshot ?? $item->unit_price), 2, '.', ''),
                'line_total' => number_format((float) ($item->line_total ?? $item->total_price), 2, '.', ''),
                'subtotal' => number_format((float) ($item->line_total ?? $item->total_price), 2, '.', ''),
                'currency' => $item->currency_snapshot ?? $item->currency ?? $this->currency,
                'shipping_method' => $item->shipping_mode_snapshot ?? $item->shipping_method,
                'shipping_price' => $item->shipping_price_snapshot ?? $item->shipping_price,
                'shipping_subtotal' => $item->shipping_subtotal,
                'delivery_status' => $item->delivery_status,
            ])->values()->all(),
            'summary' => [
                'subtotal' => $this->subtotal,
                'shipping' => $this->shipping_total,
                'shipping_total' => $this->shipping_total,
                'tax_total' => $this->tax_total,
                'discount' => $this->discount_total,
                'discount_total' => $this->discount_total,
                'grand_total' => $this->grand_total,
                'total' => $this->grand_total,
            ],
            'payment' => [
                'payment_status' => $payment?->status?->value,
                'payment_method' => $payment?->method?->value,
            ],
            'delivery_option' => $this->when(
                $this->relationLoaded('deliveryOption'),
                fn () => $this->deliveryOption
                    ? new DeliveryOptionResource($this->deliveryOption)
                    : null,
            ),
            'shipment' => [
                'status' => 'Preparing',
            ],
        ];
    }
}
