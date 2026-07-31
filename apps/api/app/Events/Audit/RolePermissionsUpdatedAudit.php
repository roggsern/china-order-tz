<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Role;

class RolePermissionsUpdatedAudit extends BusinessAuditEvent
{
    /**
     * @param  list<string>  $added
     * @param  list<string>  $removed
     */
    public static function fromRole(
        Role $role,
        Admin $actor,
        array $added,
        array $removed,
    ): self {
        return self::make(
            type: ActivityEventType::RolePermissionsUpdated,
            actorType: ActivityActorType::Admin,
            actorId: $actor->id,
            subjectType: Role::class,
            subjectId: $role->id,
            description: sprintf(
                'Permissions for role %s were updated (%d added, %d removed).',
                $role->slug,
                count($added),
                count($removed),
            ),
            oldValues: [
                'removed_permissions' => $removed,
            ],
            newValues: [
                'added_permissions' => $added,
            ],
            metadata: [
                'role_id' => $role->id,
                'role_slug' => $role->slug,
                'added_permissions' => $added,
                'removed_permissions' => $removed,
                'actor_admin_id' => $actor->id,
                'actor_admin_email' => $actor->email,
            ],
        );
    }
}
