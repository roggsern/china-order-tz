<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;

class AdminDeactivatedAudit extends BusinessAuditEvent
{
    public static function fromAdmin(Admin $admin, Admin $actor): self
    {
        return self::make(
            type: ActivityEventType::AdminDeactivated,
            actorType: ActivityActorType::Admin,
            actorId: $actor->id,
            subjectType: Admin::class,
            subjectId: $admin->id,
            description: sprintf('Admin account %s was deactivated.', $admin->email),
            oldValues: ['is_active' => true],
            newValues: ['is_active' => false],
            metadata: [
                'target_admin_id' => $admin->id,
                'target_admin_email' => $admin->email,
            ],
        );
    }
}
