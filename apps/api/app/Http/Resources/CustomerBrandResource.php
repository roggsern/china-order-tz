<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Brand */
class CustomerBrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logo,
            'banner' => $this->banner,
            'country' => $this->country,
            'is_featured' => (bool) $this->is_featured,
        ];
    }
}
