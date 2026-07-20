<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Promotion;

class PromotionActivatedAudit extends BusinessAuditEvent
{
    public static function fromPromotion(Promotion $promotion, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PromotionActivated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Promotion::class,
            subjectId: $promotion->id,
            description: 'Promotion activated: '.$promotion->name,
            newValues: ['status' => $promotion->status?->value, 'code' => $promotion->code],
        );
    }
}
