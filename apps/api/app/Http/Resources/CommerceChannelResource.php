<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CommerceChannel */
class CommerceChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $code = \App\Enums\CommerceChannelCode::tryFrom((string) $this->code);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'admin_label' => $code?->label() ?? $this->name,
            'customer_label' => $code?->customerSourceLabel(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
