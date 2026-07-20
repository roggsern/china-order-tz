<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PaymentTransaction */
class PaymentTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'provider' => $this->provider?->value ?? $this->provider,
            'provider_reference' => $this->provider_reference,
            'external_transaction_id' => $this->external_transaction_id,
            'merchant_reference' => $this->merchant_reference,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'status' => $this->status?->value ?? $this->status,
            'checkout_url' => $this->checkout_url,
            'success_indicator' => $this->success_indicator,
            'request_payload' => $this->request_payload,
            'response_payload' => $this->response_payload,
            'verification_payload' => $this->verification_payload,
            'initiated_at' => $this->initiated_at,
            'callback_received_at' => $this->callback_received_at,
            'completed_at' => $this->completed_at,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->status?->value ?? $this->order->status,
                'grand_total' => $this->order->grand_total,
                'currency' => $this->order->currency,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
