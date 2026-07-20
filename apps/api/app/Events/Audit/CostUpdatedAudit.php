<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\OrderCostSnapshot;

class CostUpdatedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $before
     */
    public static function fromSnapshot(OrderCostSnapshot $snapshot, array $before, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CostUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: OrderCostSnapshot::class,
            subjectId: $snapshot->id,
            description: 'Order cost snapshot other_cost was adjusted.',
            oldValues: $before,
            newValues: [
                'other_cost' => $snapshot->other_cost,
                'total_cost' => $snapshot->total_cost,
            ],
        );
    }
}
