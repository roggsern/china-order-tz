<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\User;

class CustomerPasswordResetCompletedAudit extends BusinessAuditEvent
{
    public static function forUser(User $user): self
    {
        return self::make(
            type: ActivityEventType::CustomerPasswordResetCompleted,
            actorType: ActivityActorType::Customer,
            actorId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            description: 'Customer password reset completed',
            oldValues: null,
            newValues: [
                'email' => $user->email,
                'sessions_revoked' => true,
            ],
            metadata: [
                'user_id' => $user->id,
            ],
        );
    }
}
