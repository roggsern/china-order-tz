<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RefundTransaction */
class RefundTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return [
            'id' => $this->id,
            'return_request_id' => $this->return_request_id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'payment_id' => $this->payment_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $status,
            'status_label' => $this->status instanceof \App\Enums\RefundTransactionStatus
                ? $this->status->label()
                : null,
            'method' => $this->method,
            'reference' => $this->reference,
            'provider_reference' => $this->provider_reference,
            'notes' => $this->notes,
            'reason' => $this->reason,
            'created_by_admin_id' => $this->created_by_admin_id,
            'approved_by_admin_id' => $this->approved_by_admin_id,
            'processed_by_admin_id' => $this->processed_by_admin_id,
            'rejected_by_admin_id' => $this->rejected_by_admin_id,
            'reviewed_at' => $this->reviewed_at,
            'approved_at' => $this->approved_at,
            'processed_at' => $this->processed_at,
            'completed_at' => $this->completed_at,
            'rejected_at' => $this->rejected_at,
            'failed_at' => $this->failed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order?->id,
                'order_number' => $this->order?->order_number,
                'status' => $this->order?->status instanceof \BackedEnum ? $this->order->status->value : $this->order?->status,
                'total' => $this->order?->total,
                'currency' => $this->order?->currency,
                'customer' => $this->order?->relationLoaded('user') && $this->order?->user
                    ? [
                        'id' => $this->order->user->id,
                        'name' => $this->order->user->name,
                        'email' => $this->order->user->email,
                    ]
                    : null,
            ]),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
            ] : null),
            'payment' => $this->whenLoaded('payment', fn () => $this->payment ? [
                'id' => $this->payment->id,
                'amount' => $this->payment->amount,
                'status' => $this->payment->status instanceof \BackedEnum ? $this->payment->status->value : $this->payment->status,
                'method' => $this->payment->method ?? null,
            ] : null),
        ];
    }
}
