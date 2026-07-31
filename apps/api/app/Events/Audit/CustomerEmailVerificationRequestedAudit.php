<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\User;

class CustomerEmailVerificationRequestedAudit extends BusinessAuditEvent
{
    public static function forUser(User $user): self
    {
        return self::make(
            type: ActivityEventType::CustomerEmailVerificationRequested,
            actorType: ActivityActorType::Customer,
            actorId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            description: 'Customer email verification requested',
            metadata: [
                'user_id' => $user->id,
                'email' => $user->email,
            ],
        );
    }
}
