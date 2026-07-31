<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\EmailChangeRequest;
use App\Models\User;

class CustomerEmailChangeRequestedAudit extends BusinessAuditEvent
{
    public static function forRequest(User $user, EmailChangeRequest $request): self
    {
        return self::make(
            type: ActivityEventType::CustomerEmailChangeRequested,
            actorType: ActivityActorType::Customer,
            actorId: $user->id,
            subjectType: EmailChangeRequest::class,
            subjectId: $request->id,
            description: 'Customer email change requested',
            oldValues: [
                'email' => $request->old_email,
            ],
            newValues: [
                'pending_email' => $request->new_email,
            ],
            metadata: [
                'user_id' => $user->id,
                'expires_at' => $request->expires_at?->toIso8601String(),
            ],
        );
    }
}
