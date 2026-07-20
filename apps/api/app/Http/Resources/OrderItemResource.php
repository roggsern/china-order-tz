<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OrderItem */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'product_name' => $this->product_name_snapshot ?? $this->product_name,
            'variant_name' => $this->variant_name_snapshot ?? $this->variant_name,
            'sku' => $this->sku_snapshot ?? $this->sku,
            'product_name_snapshot' => $this->product_name_snapshot,
            'product_slug_snapshot' => $this->product_slug_snapshot,
            'sku_snapshot' => $this->sku_snapshot,
            'brand_name_snapshot' => $this->brand_name_snapshot,
            'variant_name_snapshot' => $this->variant_name_snapshot,
            'variant_sku_snapshot' => $this->variant_sku_snapshot,
            'currency_snapshot' => $this->currency_snapshot ?? $this->currency,
            'unit_price_snapshot' => $this->unit_price_snapshot ?? $this->unit_price,
            'shipping_mode_snapshot' => $this->shipping_mode_snapshot,
            'shipping_price_snapshot' => $this->shipping_price_snapshot,
            'shipping_notes_snapshot' => $this->shipping_notes_snapshot,
            'attributes_snapshot' => $this->attributes_snapshot,
            'product_image_snapshot' => $this->product_image_snapshot ?? $this->image_snapshot,
            'image_snapshot' => $this->product_image_snapshot ?? $this->image_snapshot,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price_snapshot ?? $this->unit_price,
            'line_total' => $this->line_total ?? $this->total_price,
            'total_price' => $this->line_total ?? $this->total_price,
            'currency' => $this->currency_snapshot ?? $this->currency,
            'shipping_method' => $this->shipping_mode_snapshot ?? $this->shipping_method,
            'shipping_price' => $this->shipping_price_snapshot ?? $this->shipping_price,
            'shipping_subtotal' => $this->shipping_subtotal,
            'delivery_status' => $this->delivery_status,
            // Live catalog relation only for explicit "View Product" links.
            'product' => new ProductResource($this->whenLoaded('product')),
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
        ];
    }
}
