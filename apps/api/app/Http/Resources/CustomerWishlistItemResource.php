<?php

namespace App\Http\Resources;

use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WishlistItem */
class CustomerWishlistItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product?->id,
                'slug' => $this->product?->slug,
                'name' => $this->product?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
