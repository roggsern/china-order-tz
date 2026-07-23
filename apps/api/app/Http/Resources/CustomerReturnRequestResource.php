<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-safe allowlist for return requests.
 * Never serializes admin notes, approver data, or procurement fields — even if loaded.
 *
 * @mixin \App\Models\ReturnRequest
 */
class CustomerReturnRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'reason' => $this->reason,
            'description' => $this->description,
            'customer_notes' => $this->customer_notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->status instanceof \BackedEnum
                    ? $this->order->status->value
                    : $this->order->status,
            ] : null),
            'items' => ReturnItemResource::collection($this->whenLoaded('items')),
            'refunds' => RefundTransactionResource::collection(
                $this->whenLoaded('refundTransactions')
            ),
        ];
    }
}
