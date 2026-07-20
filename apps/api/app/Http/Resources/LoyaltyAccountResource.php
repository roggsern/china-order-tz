<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LoyaltyAccount */
class LoyaltyAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loyalty_number' => $this->loyalty_number,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'points_balance' => (int) $this->points_balance,
            'lifetime_points' => (int) $this->lifetime_points,
            'lifetime_redeemed' => (int) $this->lifetime_redeemed,
            'tier' => $this->whenLoaded('tier', fn () => $this->tier ? [
                'id' => $this->tier->id,
                'code' => $this->tier->code,
                'name' => $this->tier->name,
                'earn_multiplier' => $this->tier->earn_multiplier,
                'benefits' => $this->tier->benefits,
            ] : null),
            'customer' => $this->whenLoaded('profile', fn () => [
                'id' => $this->profile?->id,
                'customer_code' => $this->profile?->customer_code,
                'user_id' => $this->profile?->user_id,
                'name' => $this->profile?->user?->name,
                'email' => $this->profile?->user?->email,
                'phone' => $this->profile?->user?->phone,
            ]),
            'enrolled_at' => $this->enrolled_at,
            'tier_updated_at' => $this->tier_updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
