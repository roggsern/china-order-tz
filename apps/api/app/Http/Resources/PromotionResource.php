<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Promotion */
class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'discount_type' => $this->discount_type instanceof \BackedEnum
                ? $this->discount_type->value
                : $this->discount_type,
            'value' => $this->value,
            'currency' => $this->currency,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'usage_limit' => $this->usage_limit,
            'per_customer_limit' => $this->per_customer_limit,
            'minimum_order_amount' => $this->minimum_order_amount,
            'rules' => PromotionRuleResource::collection($this->whenLoaded('rules')),
            'usages_count' => $this->whenCounted('usages'),
            'rules_count' => $this->whenCounted('rules'),
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
