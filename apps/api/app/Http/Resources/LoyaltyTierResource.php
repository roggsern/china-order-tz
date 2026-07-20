<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LoyaltyTier */
class LoyaltyTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => (int) $this->sort_order,
            'min_lifetime_points' => (int) $this->min_lifetime_points,
            'min_lifetime_spend' => $this->min_lifetime_spend,
            'min_orders' => (int) $this->min_orders,
            'earn_multiplier' => $this->earn_multiplier,
            'is_active' => (bool) $this->is_active,
            'benefits' => $this->benefits,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
