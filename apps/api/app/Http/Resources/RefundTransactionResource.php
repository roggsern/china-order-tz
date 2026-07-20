<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RefundTransaction */
class RefundTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_request_id' => $this->return_request_id,
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof \App\Enums\RefundTransactionStatus
                ? $this->status->label()
                : null,
            'method' => $this->method,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
