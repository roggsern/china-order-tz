<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;

class AdminCreatedAudit extends BusinessAuditEvent
{
    public static function fromAdmin(Admin $admin, Admin $actor): self
    {
        $admin->loadMissing('role');

        return self::make(
            type: ActivityEventType::AdminCreated,
            actorType: ActivityActorType::Admin,
            actorId: $actor->id,
            subjectType: Admin::class,
            subjectId: $admin->id,
            description: sprintf('Admin account %s (%s) was created.', $admin->name, $admin->email),
            newValues: [
                'name' => $admin->name,
                'email' => $admin->email,
                'phone' => $admin->phone,
                'role_id' => $admin->role_id,
                'role_slug' => $admin->role?->slug,
                'is_active' => $admin->is_active,
            ],
            metadata: [
                'target_admin_id' => $admin->id,
                'target_admin_email' => $admin->email,
            ],
        );
    }
}
