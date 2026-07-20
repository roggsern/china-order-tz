<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Promotion;

class PromotionExpiredAudit extends BusinessAuditEvent
{
    public static function fromPromotion(Promotion $promotion, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PromotionExpired,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Promotion::class,
            subjectId: $promotion->id,
            description: 'Promotion expired: '.$promotion->name,
            newValues: ['status' => $promotion->status?->value],
        );
    }
}
