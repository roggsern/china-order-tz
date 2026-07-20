<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Promotion;

class PromotionUpdatedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $before
     */
    public static function fromPromotion(Promotion $promotion, array $before, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PromotionUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Promotion::class,
            subjectId: $promotion->id,
            description: 'Promotion updated: '.$promotion->name,
            oldValues: $before,
            newValues: [
                'name' => $promotion->name,
                'code' => $promotion->code,
                'status' => $promotion->status?->value,
                'value' => $promotion->value,
            ],
        );
    }
}
