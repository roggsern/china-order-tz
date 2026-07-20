<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReturnRequest */
class ReturnRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof \App\Enums\ReturnRequestStatus
                ? $this->status->label()
                : null,
            'reason' => $this->reason,
            'description' => $this->description,
            'customer_notes' => $this->customer_notes,
            'admin_notes' => $this->admin_notes,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->status instanceof \BackedEnum
                    ? $this->order->status->value
                    : $this->order->status,
                'total' => $this->order->total,
                'currency' => $this->order->currency,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
            ] : null),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
                'email' => $this->approver->email,
            ] : null),
            'items' => ReturnItemResource::collection($this->whenLoaded('items')),
            'refund_transactions' => RefundTransactionResource::collection(
                $this->whenLoaded('refundTransactions')
            ),
            'latest_refund' => $this->when(
                $this->relationLoaded('latestRefund'),
                fn () => $this->latestRefund
                    ? new RefundTransactionResource($this->latestRefund)
                    : null
            ),
        ];
    }
}
