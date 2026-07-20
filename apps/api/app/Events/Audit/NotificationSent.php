<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Notification;

class NotificationSent extends BusinessAuditEvent
{
    public static function fromNotification(Notification $notification): self
    {
        $status = $notification->status instanceof \BackedEnum
            ? $notification->status->value
            : (string) $notification->status;
        $channel = $notification->channel instanceof \BackedEnum
            ? $notification->channel->value
            : (string) $notification->channel;

        return self::make(
            type: ActivityEventType::NotificationSent,
            actorType: ActivityActorType::System,
            actorId: null,
            subjectType: Notification::class,
            subjectId: $notification->id,
            description: sprintf(
                'Notification %s via %s — status %s.',
                $notification->event_type ?? $notification->type?->value ?? 'unknown',
                $channel,
                $status,
            ),
            newValues: [
                'channel' => $channel,
                'status' => $status,
                'provider' => $notification->provider,
                'template_key' => $notification->template_key,
                'title' => $notification->title,
            ],
            metadata: [
                'customer_id' => $notification->customer_id ?? $notification->user_id,
                'error_message' => $notification->error_message,
            ],
            action: $status === 'sent' ? 'sent' : 'delivery_attempted',
        );
    }
}
