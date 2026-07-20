<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Promotion;

class PromotionCreatedAudit extends BusinessAuditEvent
{
    public static function fromPromotion(Promotion $promotion, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PromotionCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Promotion::class,
            subjectId: $promotion->id,
            description: 'Promotion created: '.$promotion->name,
            newValues: [
                'name' => $promotion->name,
                'code' => $promotion->code,
                'type' => $promotion->type?->value,
                'discount_type' => $promotion->discount_type?->value,
                'status' => $promotion->status?->value,
            ],
        );
    }
}
