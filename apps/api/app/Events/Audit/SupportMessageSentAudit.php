<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Enums\SupportMessageSenderType;
use App\Models\Admin;
use App\Models\SupportMessage;
use App\Models\User;

class SupportMessageSentAudit extends BusinessAuditEvent
{
    public static function fromMessage(SupportMessage $message, Admin|User|null $actor = null): self
    {
        $message->loadMissing('ticket');

        $actorType = match ($message->sender_type) {
            SupportMessageSenderType::Admin => ActivityActorType::Admin,
            SupportMessageSenderType::Customer => ActivityActorType::Customer,
            default => ActivityActorType::System,
        };

        return self::make(
            type: ActivityEventType::SupportMessageSent,
            actorType: $actorType,
            actorId: $actor instanceof Admin || $actor instanceof User ? $actor->id : $message->sender_id,
            subjectType: SupportMessage::class,
            subjectId: $message->id,
            description: sprintf(
                'Support message on ticket %s',
                $message->ticket?->ticket_number ?? $message->ticket_id,
            ),
            newValues: [
                'ticket_id' => $message->ticket_id,
                'sender_type' => $message->sender_type?->value,
            ],
            metadata: [
                'ticket_number' => $message->ticket?->ticket_number,
            ],
        );
    }
}
