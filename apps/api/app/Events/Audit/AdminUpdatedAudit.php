<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;

class AdminUpdatedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function fromAdmin(Admin $admin, Admin $actor, array $before, array $after): self
    {
        return self::make(
            type: ActivityEventType::AdminUpdated,
            actorType: ActivityActorType::Admin,
            actorId: $actor->id,
            subjectType: Admin::class,
            subjectId: $admin->id,
            description: sprintf('Admin account %s (%s) was updated.', $admin->name, $admin->email),
            oldValues: $before,
            newValues: $after,
            metadata: [
                'target_admin_id' => $admin->id,
                'target_admin_email' => $admin->email,
            ],
        );
    }
}
