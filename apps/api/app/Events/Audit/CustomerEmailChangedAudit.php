<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\User;

class CustomerEmailChangedAudit extends BusinessAuditEvent
{
    public static function forUser(User $user, string $oldEmail, string $newEmail): self
    {
        return self::make(
            type: ActivityEventType::CustomerEmailChanged,
            actorType: ActivityActorType::Customer,
            actorId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            description: 'Customer email changed',
            oldValues: [
                'email' => $oldEmail,
            ],
            newValues: [
                'email' => $newEmail,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ],
            metadata: [
                'user_id' => $user->id,
            ],
        );
    }
}
