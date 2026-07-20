<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LoyaltyEarnRule */
class LoyaltyEarnRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'rule_type' => $this->rule_type instanceof \BackedEnum
                ? $this->rule_type->value
                : $this->rule_type,
            'is_active' => (bool) $this->is_active,
            'priority' => (int) $this->priority,
            'spend_amount' => $this->spend_amount,
            'points_awarded' => (int) $this->points_awarded,
            'product_id' => $this->product_id,
            'category_id' => $this->category_id,
            'promotion_id' => $this->promotion_id,
            'bonus_points' => $this->bonus_points,
            'expiry_months' => $this->expiry_months,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'config' => $this->config,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
