<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderEngineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'user_id' => $this->user_id,
            'checkout_session_id' => $this->checkout_session_id,
            'status' => $this->status?->value ?? $this->status,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'shipping_total' => $this->shipping_total,
            'grand_total' => $this->grand_total,
            'placed_at' => $this->placed_at,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
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
                'unit_price' => $item->unit_price_snapshot ?? $item->unit_price,
                'line_total' => $item->line_total,
                'currency' => $item->currency_snapshot ?? $item->currency ?? $this->currency,
            ])->values()->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
