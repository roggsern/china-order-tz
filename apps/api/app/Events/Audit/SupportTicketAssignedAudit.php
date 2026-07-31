<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\SupportTicket;

class SupportTicketAssignedAudit extends BusinessAuditEvent
{
    public static function fromTicket(
        SupportTicket $ticket,
        ?string $previousAdminId,
        Admin $actor,
    ): self {
        return self::make(
            type: ActivityEventType::SupportTicketAssigned,
            actorType: ActivityActorType::Admin,
            actorId: $actor->id,
            subjectType: SupportTicket::class,
            subjectId: $ticket->id,
            description: sprintf('Support ticket assigned: %s', $ticket->ticket_number),
            oldValues: ['assigned_admin_id' => $previousAdminId],
            newValues: ['assigned_admin_id' => $ticket->assigned_admin_id],
            metadata: [
                'ticket_number' => $ticket->ticket_number,
                'customer_id' => $ticket->customer_id,
            ],
        );
    }
}
