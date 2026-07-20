<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CustomerTag */
class CustomerTagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'assigned_at' => $this->whenPivotLoaded('customer_profile_tag', fn () => $this->pivot->assigned_at),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
