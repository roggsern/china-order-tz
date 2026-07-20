<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\CustomerNote;

class CustomerNoteDeletedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function fromSnapshot(array $snapshot, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CustomerNoteDeleted,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CustomerNote::class,
            subjectId: $snapshot['id'] ?? null,
            description: 'Internal customer note deleted',
            oldValues: $snapshot,
        );
    }
}
