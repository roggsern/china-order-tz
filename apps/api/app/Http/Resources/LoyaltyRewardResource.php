<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LoyaltyReward */
class LoyaltyRewardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'reward_type' => $this->reward_type instanceof \BackedEnum
                ? $this->reward_type->value
                : $this->reward_type,
            'is_active' => (bool) $this->is_active,
            'points_cost' => (int) $this->points_cost,
            'discount_type' => $this->discount_type instanceof \BackedEnum
                ? $this->discount_type->value
                : $this->discount_type,
            'discount_value' => $this->discount_value,
            'product_id' => $this->product_id,
            'usage_limit' => $this->usage_limit,
            'per_customer_limit' => $this->per_customer_limit,
            'redemption_count' => (int) $this->redemption_count,
            'channels' => $this->channels,
            'config' => $this->config,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
