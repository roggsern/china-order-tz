<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreUserAssignment;

class StoreTeamUpdatedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $previous
     */
    public static function fromAssignment(
        StoreUserAssignment $assignment,
        array $previous,
        ?Admin $actor = null,
    ): self {
        return self::make(
            type: ActivityEventType::StoreTeamUpdated,
            actorType: $actor ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $actor?->id,
            subjectType: Store::class,
            subjectId: $assignment->store_id,
            description: 'Store team assignment updated',
            oldValues: $previous,
            newValues: [
                'admin_id' => $assignment->admin_id,
                'store_id' => $assignment->store_id,
                'operational_scope' => $assignment->operational_scope?->value,
                'assignment_type' => $assignment->assignment_type?->value,
                'is_active' => $assignment->is_active,
            ],
            metadata: [
                'assignment_id' => $assignment->id,
                'affected_admin_id' => $assignment->admin_id,
            ],
        );
    }
}
