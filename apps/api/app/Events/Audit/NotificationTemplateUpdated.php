<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\NotificationTemplate;

class NotificationTemplateUpdated extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function fromTemplate(
        NotificationTemplate $template,
        array $oldValues,
        array $newValues,
        ?Admin $admin = null,
    ): self {
        return self::make(
            type: ActivityEventType::NotificationTemplateUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: NotificationTemplate::class,
            subjectId: $template->id,
            description: sprintf('Notification template "%s" was updated.', $template->key),
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: [
                'key' => $template->key,
                'channel' => $template->channel instanceof \BackedEnum
                    ? $template->channel->value
                    : $template->channel,
            ],
        );
    }
}
