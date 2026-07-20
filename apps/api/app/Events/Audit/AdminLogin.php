<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;

class AdminLogin extends BusinessAuditEvent
{
    public static function fromAdmin(Admin $admin, ?string $ip = null, ?string $ua = null): self
    {
        return self::make(
            type: ActivityEventType::AdminLogin,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: Admin::class,
            subjectId: $admin->id,
            description: sprintf('Admin %s logged in.', $admin->email),
            newValues: [
                'email' => $admin->email,
                'name' => $admin->name,
            ],
            ipAddress: $ip,
            userAgent: $ua,
        );
    }
}
