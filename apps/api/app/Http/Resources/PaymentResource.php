<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'transaction_id' => $this->transaction_id,
            'reference' => $this->reference,
            'paid_at' => $this->paid_at,
            'metadata' => $this->metadata,
            'order' => new OrderResource($this->whenLoaded('order')),
            'created_at' => $this->created_at,
        ];
    }
}
