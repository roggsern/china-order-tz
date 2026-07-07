<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutPreparationResource extends JsonResource
{
    /**
     * @param  array{
     *     checkout_type: string,
     *     cart: \App\Models\Cart,
     *     subtotal: string,
     *     item_count: int,
     *     ready_for_checkout: bool
     * }  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'checkout_type' => $this->resource['checkout_type'],
            'cart' => new CartResource($this->resource['cart']),
            'subtotal' => $this->resource['subtotal'],
            'item_count' => $this->resource['item_count'],
            'ready_for_checkout' => $this->resource['ready_for_checkout'],
        ];
    }
}
