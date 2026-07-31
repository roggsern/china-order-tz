<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\User;

class CustomerEmailVerifiedAudit extends BusinessAuditEvent
{
    public static function forUser(User $user): self
    {
        return self::make(
            type: ActivityEventType::CustomerEmailVerified,
            actorType: ActivityActorType::Customer,
            actorId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            description: 'Customer email verified',
            newValues: [
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ],
            metadata: [
                'user_id' => $user->id,
            ],
        );
    }
}
