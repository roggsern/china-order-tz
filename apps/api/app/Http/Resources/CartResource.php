<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Cart */
class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subtotal = $this->when(
            $this->relationLoaded('items'),
            fn () => $this->subtotal(),
        );

        return [
            'id' => $this->id,
            'status' => $this->status,
            'currency' => $this->currency ?? 'TZS',
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'item_count' => $this->when(
                $this->relationLoaded('items'),
                fn () => $this->itemCount(),
            ),
            'is_empty' => $this->when(
                $this->relationLoaded('items'),
                fn () => $this->isEmpty(),
            ),
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'updated_at' => $this->updated_at,
        ];
    }
}
