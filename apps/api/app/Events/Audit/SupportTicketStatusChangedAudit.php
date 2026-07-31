<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\SupportTicket;

class SupportTicketStatusChangedAudit extends BusinessAuditEvent
{
    public static function fromTicket(
        SupportTicket $ticket,
        string $previousStatus,
        ?Admin $actor = null,
    ): self {
        return self::make(
            type: ActivityEventType::SupportTicketStatusChanged,
            actorType: $actor ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $actor?->id,
            subjectType: SupportTicket::class,
            subjectId: $ticket->id,
            description: sprintf(
                'Support ticket %s status: %s → %s',
                $ticket->ticket_number,
                $previousStatus,
                $ticket->status?->value,
            ),
            oldValues: ['status' => $previousStatus],
            newValues: ['status' => $ticket->status?->value],
            metadata: ['ticket_number' => $ticket->ticket_number],
        );
    }
}
