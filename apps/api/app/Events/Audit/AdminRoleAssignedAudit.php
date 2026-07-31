<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Role;

class AdminRoleAssignedAudit extends BusinessAuditEvent
{
    public static function fromAdmin(Admin $admin, Admin $actor, ?Role $previousRole, Role $newRole): self
    {
        return self::make(
            type: ActivityEventType::AdminRoleAssigned,
            actorType: ActivityActorType::Admin,
            actorId: $actor->id,
            subjectType: Admin::class,
            subjectId: $admin->id,
            description: sprintf(
                'Role for admin %s changed from %s to %s.',
                $admin->email,
                $previousRole?->slug ?? 'none',
                $newRole->slug,
            ),
            oldValues: [
                'role_id' => $previousRole?->id,
                'role_slug' => $previousRole?->slug,
            ],
            newValues: [
                'role_id' => $newRole->id,
                'role_slug' => $newRole->slug,
            ],
            metadata: [
                'target_admin_id' => $admin->id,
                'target_admin_email' => $admin->email,
            ],
        );
    }
}
