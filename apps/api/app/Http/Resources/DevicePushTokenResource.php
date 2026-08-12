<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DevicePushToken */
class DevicePushTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider instanceof \BackedEnum
                ? $this->provider->value
                : $this->provider,
            'platform' => $this->platform instanceof \BackedEnum
                ? $this->platform->value
                : $this->platform,
            'installation_id' => $this->installation_id,
            'is_active' => $this->isActive(),
            'last_seen_at' => $this->last_seen_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // push_token intentionally omitted from API responses.
        ];
    }
}
