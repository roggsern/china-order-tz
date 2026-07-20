<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GrowthSegment */
class GrowthSegmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'rules' => $this->rules,
            'is_active' => (bool) $this->is_active,
            'store_id' => $this->store_id,
            'store' => $this->whenLoaded('store', fn () => [
                'id' => $this->store?->id,
                'code' => $this->store?->code,
                'name' => $this->store?->name,
            ]),
            'member_count' => (int) $this->member_count,
            'last_evaluated_at' => $this->last_evaluated_at,
            'created_at' => $this->created_at,
        ];
    }
}
