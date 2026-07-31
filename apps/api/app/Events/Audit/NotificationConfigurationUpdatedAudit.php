<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;

class NotificationConfigurationUpdatedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function fromChange(array $before, array $after, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::NotificationConfigurationUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: 'notification_configuration',
            subjectId: 'notifications',
            description: 'Notification delivery configuration was updated.',
            oldValues: $before,
            newValues: $after,
            metadata: [
                'group' => 'notifications',
            ],
        );
    }
}
