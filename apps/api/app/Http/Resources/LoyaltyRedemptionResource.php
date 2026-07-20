<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LoyaltyRedemption */
class LoyaltyRedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loyalty_account_id' => $this->loyalty_account_id,
            'loyalty_reward_id' => $this->loyalty_reward_id,
            'reward' => new LoyaltyRewardResource($this->whenLoaded('reward')),
            'promotion_id' => $this->promotion_id,
            'promotion_code' => $this->promotion_code,
            'order_id' => $this->order_id,
            'channel' => $this->channel,
            'status' => $this->status,
            'points_spent' => (int) $this->points_spent,
            'issued_at' => $this->issued_at,
            'applied_at' => $this->applied_at,
            'account' => new LoyaltyAccountResource($this->whenLoaded('account')),
            'created_at' => $this->created_at,
        ];
    }
}
