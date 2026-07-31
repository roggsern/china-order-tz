<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\User;

class CustomerPasswordResetRequestedAudit extends BusinessAuditEvent
{
    public static function forUser(User $user): self
    {
        return self::make(
            type: ActivityEventType::CustomerPasswordResetRequested,
            actorType: ActivityActorType::Customer,
            actorId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            description: 'Customer password reset requested',
            oldValues: null,
            newValues: [
                'email' => $user->email,
            ],
            metadata: [
                'user_id' => $user->id,
            ],
        );
    }
}
