<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\CustomerNote;

class CustomerNoteAddedAudit extends BusinessAuditEvent
{
    public static function fromNote(CustomerNote $note, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CustomerNoteAdded,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CustomerNote::class,
            subjectId: $note->id,
            description: 'Internal customer note added',
            newValues: [
                'customer_profile_id' => $note->customer_profile_id,
                'is_pinned' => $note->is_pinned,
            ],
            metadata: ['body_preview' => mb_strimwidth($note->body, 0, 80, '…')],
        );
    }
}
