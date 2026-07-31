<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\User;

class CustomerLogoutAudit extends BusinessAuditEvent
{
    public static function fromUser(User $user, ?string $ip = null, ?string $ua = null): self
    {
        return self::make(
            type: ActivityEventType::CustomerLogout,
            actorType: ActivityActorType::Customer,
            actorId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            description: sprintf('Customer %s logged out.', $user->email),
            metadata: [
                'email' => $user->email,
                'user_id' => $user->id,
                'current_token_revoked' => true,
            ],
            ipAddress: $ip,
            userAgent: $ua,
        );
    }
}
