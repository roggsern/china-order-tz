<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class AdminOrderIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof OrderStatus
            ? $this->status
            : OrderStatus::tryFrom((string) $this->status);

        $channelCode = $this->resolveCommerceChannelCode();

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'commerce_channel_id' => $this->commerce_channel_id,
            'commerce_channel_code' => $channelCode,
            'commerce_channel' => new CommerceChannelResource($this->whenLoaded('commerceChannel')),
            'status' => $status?->value ?? (string) $this->status,
            'status_label' => $status?->label() ?? 'Unknown status',
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'shipping_amount' => $this->shipping_amount,
            'shipping_total' => $this->shipping_total,
            'total' => $this->total,
            'grand_total' => $this->grand_total,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'placed_at' => $this->placed_at,
            'paid_at' => $this->paid_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'items' => AdminOrderIndexItemResource::collection($this->whenLoaded('items')),
            'payments' => $this->when(
                $this->relationLoaded('payments'),
                fn () => $this->payments->map(static function ($payment): array {
                    return [
                        'method' => $payment->method,
                        'status' => $payment->status,
                        'amount' => $payment->amount,
                        'reference' => $payment->reference,
                        'paid_at' => $payment->paid_at,
                    ];
                })->values()->all(),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function resolveCommerceChannelCode(): ?string
    {
        if ($this->relationLoaded('commerceChannel') && $this->commerceChannel !== null) {
            $code = trim((string) $this->commerceChannel->code);

            return $code !== '' ? $code : null;
        }

        $snapshot = $this->commerce_channel_snapshot;
        if (is_array($snapshot)) {
            $code = isset($snapshot['code']) ? trim((string) $snapshot['code']) : '';

            return $code !== '' ? $code : null;
        }

        return null;
    }
}
