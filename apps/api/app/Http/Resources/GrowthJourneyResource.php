<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GrowthJourney */
class GrowthJourneyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'trigger_type' => $this->trigger_type instanceof \BackedEnum
                ? $this->trigger_type->value
                : $this->trigger_type,
            'trigger_config' => $this->trigger_config,
            'growth_segment_id' => $this->growth_segment_id,
            'growth_campaign_id' => $this->growth_campaign_id,
            'segment' => $this->whenLoaded('segment', fn () => [
                'id' => $this->segment?->id,
                'name' => $this->segment?->name,
            ]),
            'campaign' => $this->whenLoaded('campaign', fn () => [
                'id' => $this->campaign?->id,
                'name' => $this->campaign?->name,
            ]),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
