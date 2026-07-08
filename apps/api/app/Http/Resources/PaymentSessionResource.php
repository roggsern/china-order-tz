<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'payment_id' => $this->id,
            'reference' => $this->reference,
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order->order_number),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->method->value,
            'status' => $this->status->value,
            'gateway_reference' => $this->gateway_reference,
            'checkout_url' => $this->checkout_url,
            'initiated_at' => $this->initiated_at,
        ];
    }
}
