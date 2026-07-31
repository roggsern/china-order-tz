<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\SupportTicket;

class SupportTicketCreatedAudit extends BusinessAuditEvent
{
    public static function fromTicket(SupportTicket $ticket, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::SupportTicketCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::Customer,
            actorId: $admin?->id ?? $ticket->customer_id,
            subjectType: SupportTicket::class,
            subjectId: $ticket->id,
            description: sprintf('Support ticket created: %s', $ticket->subject),
            newValues: [
                'ticket_number' => $ticket->ticket_number,
                'category' => $ticket->category?->value,
                'priority' => $ticket->priority?->value,
                'status' => $ticket->status?->value,
            ],
            metadata: [
                'customer_id' => $ticket->customer_id,
                'order_id' => $ticket->order_id,
            ],
        );
    }
}
