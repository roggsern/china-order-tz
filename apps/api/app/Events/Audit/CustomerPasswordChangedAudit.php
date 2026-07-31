<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\User;

class CustomerPasswordChangedAudit extends BusinessAuditEvent
{
    public static function forUser(User $user): self
    {
        return self::make(
            type: ActivityEventType::CustomerPasswordChanged,
            actorType: ActivityActorType::Customer,
            actorId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            description: 'Customer password changed',
            oldValues: null,
            newValues: [
                'email' => $user->email,
                'sessions_revoked' => true,
                'all_devices' => true,
            ],
            metadata: [
                'user_id' => $user->id,
            ],
        );
    }
}
