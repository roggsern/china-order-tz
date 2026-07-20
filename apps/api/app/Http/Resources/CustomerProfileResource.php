<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CustomerProfile */
class CustomerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'id' => $this->id,
            'customer_code' => $this->customer_code,
            'user_id' => $this->user_id,
            'name' => $user?->name,
            'first_name' => $user?->first_name,
            'last_name' => $user?->last_name,
            'email' => $user?->email,
            'phone' => $user?->phone,
            'is_active' => (bool) $user?->is_active,
            'registration_source' => $this->registration_source instanceof \BackedEnum
                ? $this->registration_source->value
                : $this->registration_source,
            'lifecycle_status' => $this->lifecycle_status instanceof \BackedEnum
                ? $this->lifecycle_status->value
                : $this->lifecycle_status,
            'blocked_at' => $this->blocked_at,
            'block_reason' => $this->block_reason,
            'preferred_language' => $this->preferred_language,
            'preferred_currency' => $this->preferred_currency,
            'marketing_opt_in' => (bool) $this->marketing_opt_in,
            'notes_summary' => $this->notes_summary,
            'registered_at' => $this->created_at,
            'metrics' => $this->whenLoaded('metrics', fn () => [
                'total_orders' => (int) $this->metrics->total_orders,
                'completed_orders' => (int) $this->metrics->completed_orders,
                'cancelled_orders' => (int) $this->metrics->cancelled_orders,
                'total_spend' => $this->metrics->total_spend,
                'total_refunds' => $this->metrics->total_refunds,
                'gross_profit_generated' => $this->metrics->gross_profit_generated,
                'average_order_value' => $this->metrics->average_order_value,
                'first_order_at' => $this->metrics->first_order_at,
                'last_order_at' => $this->metrics->last_order_at,
                'last_payment_at' => $this->metrics->last_payment_at,
                'last_activity_at' => $this->metrics->last_activity_at,
                'currency' => $this->metrics->currency,
                'calculated_at' => $this->metrics->calculated_at,
            ]),
            'tags' => CustomerTagResource::collection($this->whenLoaded('tags')),
            'loyalty' => $this->whenLoaded('loyaltyAccount', function () {
                $account = $this->loyaltyAccount;
                if ($account === null) {
                    return null;
                }

                return [
                    'id' => $account->id,
                    'loyalty_number' => $account->loyalty_number,
                    'status' => $account->status instanceof \BackedEnum
                        ? $account->status->value
                        : $account->status,
                    'points_balance' => (int) $account->points_balance,
                    'lifetime_points' => (int) $account->lifetime_points,
                    'lifetime_redeemed' => (int) $account->lifetime_redeemed,
                    'tier' => $account->relationLoaded('tier') && $account->tier
                        ? [
                            'id' => $account->tier->id,
                            'code' => $account->tier->code,
                            'name' => $account->tier->name,
                            'earn_multiplier' => $account->tier->earn_multiplier,
                        ]
                        : null,
                    'enrolled_at' => $account->enrolled_at,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
