<?php

namespace App\Http\Resources;

use App\Support\Admin\PermissionRiskClassifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Permission */
class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'description' => $this->description,
            'risk_tier' => PermissionRiskClassifier::classify($this->slug)->value,
        ];
    }
}
