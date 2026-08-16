<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\User;

class CustomerAccountClosedAudit extends BusinessAuditEvent
{
    public static function forUser(User $user): self
    {
        return self::make(
            type: ActivityEventType::CustomerAccountClosed,
            actorType: ActivityActorType::Customer,
            actorId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            description: 'Customer account closed',
            oldValues: null,
            newValues: [
                'is_active' => false,
                'soft_deleted' => true,
                'sessions_revoked' => true,
                'push_tokens_revoked' => true,
                'email_tombstoned' => true,
            ],
            metadata: [
                'user_id' => $user->id,
                // Never store the original email — only that identity was tombstoned.
                'identity_anonymized' => true,
            ],
        );
    }
}
