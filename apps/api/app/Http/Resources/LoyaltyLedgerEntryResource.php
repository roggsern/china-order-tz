<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LoyaltyLedgerEntry */
class LoyaltyLedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_type' => $this->entry_type instanceof \BackedEnum
                ? $this->entry_type->value
                : $this->entry_type,
            'points' => (int) $this->points,
            'balance_after' => (int) $this->balance_after,
            'reason' => $this->reason,
            'order_id' => $this->order_id,
            'loyalty_reward_id' => $this->loyalty_reward_id,
            'promotion_id' => $this->promotion_id,
            'actor_type' => $this->actor_type,
            'expires_at' => $this->expires_at,
            'expired_at' => $this->expired_at,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
