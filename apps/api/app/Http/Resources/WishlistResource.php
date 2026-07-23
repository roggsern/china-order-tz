<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Wishlist */
class WishlistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => new CustomerCartProductResource($this->whenLoaded('product')),
            'variant' => new CustomerProductVariantResource($this->whenLoaded('variant')),
            'created_at' => $this->created_at,
        ];
    }
}
