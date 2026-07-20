<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PromotionRule */
class PromotionRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rule_type' => $this->rule_type instanceof \BackedEnum
                ? $this->rule_type->value
                : $this->rule_type,
            'rule_value' => $this->rule_value,
        ];
    }
}
