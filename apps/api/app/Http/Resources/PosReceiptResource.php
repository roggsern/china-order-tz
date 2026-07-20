<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PosReceipt */
class PosReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $snap = $this->snapshot ?? [];

        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'order_id' => $this->order_id,
            'store_id' => $this->store_id,
            'pos_session_id' => $this->pos_session_id,
            'issued_at' => $this->issued_at,
            'print_count' => (int) $this->print_count,
            'last_printed_at' => $this->last_printed_at,
            'qr_payload' => $this->qr_payload,
            'snapshot' => $snap,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'total' => $this->order->total,
                'user_id' => $this->order->user_id,
                'customer_name' => $this->order->user?->name,
            ]),
            'store' => $this->whenLoaded('store', fn () => [
                'id' => $this->store->id,
                'code' => $this->store->code,
                'name' => $this->store->name,
                'theme_color' => $this->store->theme_color,
                'logo_path' => $this->store->logo_path,
            ]),
            'session' => $this->whenLoaded('session', fn () => [
                'id' => $this->session?->id,
                'terminal_code' => $this->session?->terminal?->code,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
