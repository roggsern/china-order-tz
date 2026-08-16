<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
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
            'checkout_session_id' => $this->checkout_session_id,
            'commerce_channel_id' => $this->commerce_channel_id,
            'commerce_channel_code' => $channelCode,
            'commerce_channel' => new CommerceChannelResource($this->whenLoaded('commerceChannel')),
            'status' => $status?->value ?? (string) $this->status,
            'status_label' => $status?->label() ?? 'Unknown status',
            'customer_status_label' => $status?->customerLabel() ?? 'Status unavailable',
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'discount_total' => $this->discount_total,
            'tax_amount' => $this->tax_amount,
            'tax_total' => $this->tax_total,
            'shipping_amount' => $this->shipping_amount,
            'shipping_total' => $this->shipping_total,
            'total' => $this->total,
            'grand_total' => $this->grand_total,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'placed_at' => $this->placed_at,
            'paid_at' => $this->paid_at,
            'cancelled_at' => $this->cancelled_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'coupon' => new CouponResource($this->whenLoaded('coupon')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'fulfillment' => new FulfillmentResource($this->whenLoaded('fulfillment')),
            'delivery_option' => new DeliveryOptionResource($this->whenLoaded('deliveryOption')),
            'shipping_address' => new ShippingAddressResource($this->whenLoaded('shippingAddress')),
            'refund_transactions' => RefundTransactionResource::collection($this->whenLoaded('refundTransactions')),
            'status_history' => $this->whenLoaded('statusHistory'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Canonical journey code: CHINA_IMPORT | TZ_LOCAL.
     * Prefer loaded relation, then persisted snapshot — never invent a second vocabulary.
     */
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
