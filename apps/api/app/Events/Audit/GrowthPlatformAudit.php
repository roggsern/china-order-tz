<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\GrowthCampaign;
use App\Models\GrowthJourney;
use App\Models\GrowthSegment;

class GrowthPlatformAudit extends BusinessAuditEvent
{
    public static function segmentCreated(GrowthSegment $segment, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::GrowthSegmentCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: GrowthSegment::class,
            subjectId: $segment->id,
            description: 'Growth segment created: '.$segment->name,
            newValues: [
                'code' => $segment->code,
                'member_count' => $segment->member_count,
            ],
        );
    }

    public static function campaignCreated(GrowthCampaign $campaign, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::GrowthCampaignCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: GrowthCampaign::class,
            subjectId: $campaign->id,
            description: 'Growth campaign created: '.$campaign->name,
            newValues: [
                'campaign_type' => $campaign->campaign_type instanceof \BackedEnum
                    ? $campaign->campaign_type->value
                    : $campaign->campaign_type,
                'segment_id' => $campaign->growth_segment_id,
            ],
        );
    }

    public static function campaignUpdated(GrowthCampaign $campaign, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::GrowthCampaignUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: GrowthCampaign::class,
            subjectId: $campaign->id,
            description: 'Growth campaign updated: '.$campaign->name,
        );
    }

    public static function campaignSent(GrowthCampaign $campaign, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::GrowthCampaignSent,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: GrowthCampaign::class,
            subjectId: $campaign->id,
            description: sprintf('Growth campaign sent: %s (%d recipients)', $campaign->name, $campaign->sent_count),
            newValues: [
                'sent_count' => $campaign->sent_count,
                'status' => $campaign->status instanceof \BackedEnum
                    ? $campaign->status->value
                    : $campaign->status,
            ],
        );
    }

    public static function journeyCreated(GrowthJourney $journey, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::GrowthJourneyCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: GrowthJourney::class,
            subjectId: $journey->id,
            description: 'Growth journey created: '.$journey->name,
            newValues: [
                'trigger_type' => $journey->trigger_type instanceof \BackedEnum
                    ? $journey->trigger_type->value
                    : $journey->trigger_type,
            ],
        );
    }
}
