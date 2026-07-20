<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CheckoutSession */
class CheckoutSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'cart_id' => $this->cart_id,
            'currency' => $this->currency,
            'status' => $this->status?->value ?? $this->status,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'discount_breakdown' => $this->discount_breakdown,
            'promotion_id' => $this->promotion_id,
            'applied_promotion_code' => $this->applied_promotion_code,
            'promotion' => $this->whenLoaded('promotion', fn () => $this->promotion ? [
                'id' => $this->promotion->id,
                'name' => $this->promotion->name,
                'code' => $this->promotion->code,
                'discount_type' => $this->promotion->discount_type instanceof \BackedEnum
                    ? $this->promotion->discount_type->value
                    : $this->promotion->discount_type,
                'value' => $this->promotion->value,
            ] : null),
            'tax_total' => $this->tax_total,
            'shipping_total' => $this->shipping_total,
            'grand_total' => $this->grand_total,
            'shipping_choice' => $this->shipping_choice,
            'shipping_method' => $this->shipping_method,
            'agent_name' => $this->agent_name,
            'agent_contact' => $this->agent_contact,
            'shipping_ready' => filled($this->shipping_choice),
            'is_expired' => $this->isExpired(),
            'expires_at' => $this->expires_at,
            'cart' => new CartResource($this->whenLoaded('cart')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
