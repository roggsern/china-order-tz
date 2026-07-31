<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreUserAssignment;

class StoreTeamRemovedAudit extends BusinessAuditEvent
{
    public static function fromAssignment(StoreUserAssignment $assignment, ?Admin $actor = null): self
    {
        return self::make(
            type: ActivityEventType::StoreTeamRemoved,
            actorType: $actor ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $actor?->id,
            subjectType: Store::class,
            subjectId: $assignment->store_id,
            description: 'Store team member removed',
            oldValues: [
                'admin_id' => $assignment->admin_id,
                'store_id' => $assignment->store_id,
                'operational_scope' => $assignment->operational_scope?->value,
            ],
            metadata: [
                'assignment_id' => $assignment->id,
                'affected_admin_id' => $assignment->admin_id,
            ],
        );
    }
}
