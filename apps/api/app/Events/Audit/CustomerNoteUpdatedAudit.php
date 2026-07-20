<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\CustomerNote;

class CustomerNoteUpdatedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $before
     */
    public static function fromNote(CustomerNote $note, array $before, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CustomerNoteUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CustomerNote::class,
            subjectId: $note->id,
            description: 'Internal customer note updated',
            oldValues: $before,
            newValues: $note->only(['body', 'is_pinned']),
        );
    }
}
