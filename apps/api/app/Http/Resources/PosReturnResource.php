<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReturnRequest */
class PosReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'order_id' => $this->order_id,
            'store_id' => $this->store_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'return_type' => $this->return_type instanceof \BackedEnum ? $this->return_type->value : $this->return_type,
            'reason' => $this->reason,
            'refund_method' => $this->refund_method,
            'refund_total' => $this->refund_total,
            'receipt_snapshot' => $this->receipt_snapshot,
            'completed_at' => $this->completed_at,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'total' => $this->order->total,
                'customer_name' => $this->order->user?->name,
            ]),
            'store' => $this->whenLoaded('store', fn () => [
                'id' => $this->store?->id,
                'code' => $this->store?->code,
                'name' => $this->store?->name,
            ]),
            'cashier' => $this->whenLoaded('processor', fn () => [
                'id' => $this->processor?->id,
                'name' => $this->processor?->name,
            ]),
            'return_reason' => $this->whenLoaded('returnReason', fn () => $this->returnReason ? [
                'id' => $this->returnReason->id,
                'code' => $this->returnReason->code,
                'name' => $this->returnReason->name,
            ] : null),
            'original_receipt' => $this->whenLoaded('originalReceipt', fn () => $this->originalReceipt ? [
                'id' => $this->originalReceipt->id,
                'receipt_number' => $this->originalReceipt->receipt_number,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'order_item_id' => $item->order_item_id,
                'quantity' => $item->quantity,
                'refund_amount' => $item->refund_amount,
                'inventory_disposition' => $item->inventory_disposition instanceof \BackedEnum
                    ? $item->inventory_disposition->value
                    : $item->inventory_disposition,
                'resolution' => $item->resolution instanceof \BackedEnum
                    ? $item->resolution->value
                    : $item->resolution,
                'product_name' => $item->orderItem?->product_name ?? $item->orderItem?->product_name_snapshot,
                'variant_name' => $item->orderItem?->variant_name ?? $item->orderItem?->variant_name_snapshot,
                'exchange_variant' => $item->exchangeVariant ? [
                    'id' => $item->exchangeVariant->id,
                    'name' => $item->exchangeVariant->name,
                    'sku' => $item->exchangeVariant->sku,
                ] : null,
            ])),
            'refund' => $this->whenLoaded('latestRefund', fn () => $this->latestRefund ? [
                'id' => $this->latestRefund->id,
                'amount' => $this->latestRefund->amount,
                'method' => $this->latestRefund->method,
                'status' => $this->latestRefund->status instanceof \BackedEnum
                    ? $this->latestRefund->status->value
                    : $this->latestRefund->status,
                'reference' => $this->latestRefund->reference,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
