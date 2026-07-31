<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreUserAssignment;

class StoreTeamAssignedAudit extends BusinessAuditEvent
{
    public static function fromAssignment(StoreUserAssignment $assignment, ?Admin $actor = null): self
    {
        return self::make(
            type: ActivityEventType::StoreTeamAssigned,
            actorType: $actor ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $actor?->id,
            subjectType: Store::class,
            subjectId: $assignment->store_id,
            description: sprintf('Store team member assigned (%s)', $assignment->operational_scope?->label() ?? 'member'),
            newValues: [
                'admin_id' => $assignment->admin_id,
                'store_id' => $assignment->store_id,
                'operational_scope' => $assignment->operational_scope?->value,
                'assignment_type' => $assignment->assignment_type?->value,
            ],
            metadata: [
                'assignment_id' => $assignment->id,
                'affected_admin_id' => $assignment->admin_id,
            ],
        );
    }
}
