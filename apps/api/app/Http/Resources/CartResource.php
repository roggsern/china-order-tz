<?php

namespace App\Http\Resources;

use App\Models\Cart;
use App\Services\Purchasing\PresentPurchaseQuantity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cart */
class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subtotal = $this->when(
            $this->relationLoaded('items'),
            fn () => $this->subtotal(),
        );

        if ($this->relationLoaded('items')) {
            $this->resource->setRelation(
                'items',
                $this->items->map(function ($item) {
                    $item->setRelation('cart', $this->resource);

                    return $item;
                }),
            );
        }

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
            'purchase_quantity_blockers' => $this->when(
                $this->relationLoaded('items'),
                fn () => app(PresentPurchaseQuantity::class)->blockersForCartItems($this->items),
            ),
            'updated_at' => $this->updated_at,
        ];
    }
}
