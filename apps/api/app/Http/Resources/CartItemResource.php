<?php

namespace App\Http\Resources;

use App\Services\Inventory\StockResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CartItem */
class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $available = null;
        $resolver = app(StockResolver::class);

        if (filled($this->product_variant_id) && $this->relationLoaded('variant') && $this->variant !== null) {
            $product = $this->relationLoaded('product') ? $this->product : null;
            $stock = $resolver->resolveVariantProduct($this->variant, null, $product);
            $available = $stock->resolved ? $stock->quantityAvailable : null;
        }

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'price_snapshot' => $this->price_snapshot ?? $this->unit_price,
            'currency' => $this->currency ?? 'TZS',
            'available_stock' => $available,
            'subtotal' => $this->subtotal(),
            'product' => new CustomerCartProductResource($this->whenLoaded('product')),
            'variant' => new CustomerProductVariantResource($this->whenLoaded('variant')),
        ];
    }
}
