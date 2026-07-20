<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GrowthCampaign */
class GrowthCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'campaign_type' => $this->campaign_type instanceof \BackedEnum
                ? $this->campaign_type->value
                : $this->campaign_type,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'growth_segment_id' => $this->growth_segment_id,
            'segment' => $this->whenLoaded('segment', fn () => [
                'id' => $this->segment?->id,
                'code' => $this->segment?->code,
                'name' => $this->segment?->name,
                'member_count' => $this->segment?->member_count,
            ]),
            'store_id' => $this->store_id,
            'channel' => $this->channel,
            'channels' => $this->channels,
            'message_title' => $this->message_title,
            'message_body' => $this->message_body,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'promotion_id' => $this->promotion_id,
            'promotion_code' => $this->promotion_code,
            'bonus_points' => $this->bonus_points,
            'sent_count' => (int) $this->sent_count,
            'delivered_count' => (int) $this->delivered_count,
            'opened_count' => (int) $this->opened_count,
            'clicked_count' => (int) $this->clicked_count,
            'redeemed_count' => (int) $this->redeemed_count,
            'purchased_count' => (int) $this->purchased_count,
            'revenue_generated' => $this->revenue_generated,
            'created_at' => $this->created_at,
        ];
    }
}
