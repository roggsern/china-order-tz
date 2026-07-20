<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;

class AdminLogout extends BusinessAuditEvent
{
    public static function fromAdmin(Admin $admin, ?string $ip = null, ?string $ua = null): self
    {
        return self::make(
            type: ActivityEventType::AdminLogout,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: Admin::class,
            subjectId: $admin->id,
            description: sprintf('Admin %s logged out.', $admin->email),
            metadata: [
                'email' => $admin->email,
            ],
            ipAddress: $ip,
            userAgent: $ua,
        );
    }
}
