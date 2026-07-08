<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutResource extends JsonResource
{
    /**
     * @param  array{
     *     customer: \App\Models\User,
     *     delivery_address: \App\Models\DeliveryAddress,
     *     cart: \App\Models\Cart,
     *     subtotal: string,
     *     shipping_summary: array<string, mixed>,
     *     grand_total: string,
     *     ready_for_confirmation: bool
     * }  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'customer' => [
                'first_name' => $this->resource['customer']->first_name,
                'last_name' => $this->resource['customer']->last_name,
                'email' => $this->resource['customer']->email,
                'phone' => $this->resource['customer']->phone,
            ],
            'delivery_address' => new DeliveryAddressResource($this->resource['delivery_address']),
            'items' => CheckoutItemResource::collection($this->resource['cart']->items),
            'subtotal' => $this->resource['subtotal'],
            'shipping_summary' => $this->resource['shipping_summary'],
            'grand_total' => $this->resource['grand_total'],
            'ready_for_confirmation' => $this->resource['ready_for_confirmation'],
        ];
    }
}
