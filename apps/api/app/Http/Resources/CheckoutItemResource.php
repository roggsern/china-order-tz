<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CartItem */
class CheckoutItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isChina = $this->product->requiresChinaShipping();

        $data = [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product->name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal(),
            'source' => $isChina ? 'China' : 'Dar',
        ];

        if ($isChina) {
            $data['shipping_method'] = $this->shipping_method?->value;
            $data['shipping_price'] = $this->shipping_price;
            $data['shipping_subtotal'] = $this->shippingSubtotal();
        } else {
            $data['delivery_status'] = 'To Be Negotiated';
        }

        $data['estimated_delivery_days'] = $this->estimated_delivery_days;
        $data['estimated_min_days'] = $this->estimated_min_days;
        $data['estimated_max_days'] = $this->estimated_max_days;

        return $data;
    }
}
