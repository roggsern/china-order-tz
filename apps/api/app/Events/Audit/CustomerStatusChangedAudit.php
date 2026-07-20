<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Enums\CustomerLifecycleStatus;
use App\Models\Admin;
use App\Models\CustomerProfile;

class CustomerStatusChangedAudit extends BusinessAuditEvent
{
    public static function fromChange(
        CustomerProfile $profile,
        ?CustomerLifecycleStatus $from,
        CustomerLifecycleStatus $to,
        ?Admin $admin = null,
        ?string $reason = null,
    ): self {
        $type = match ($to) {
            CustomerLifecycleStatus::Blocked => ActivityEventType::CustomerBlocked,
            default => $from === CustomerLifecycleStatus::Blocked
                ? ActivityEventType::CustomerUnblocked
                : ActivityEventType::CustomerStatusChanged,
        };

        return self::make(
            type: $type,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CustomerProfile::class,
            subjectId: $profile->id,
            description: sprintf(
                'Customer %s status %s → %s',
                $profile->customer_code,
                $from?->value ?? 'none',
                $to->value,
            ),
            oldValues: ['lifecycle_status' => $from?->value],
            newValues: [
                'lifecycle_status' => $to->value,
                'block_reason' => $reason,
            ],
            metadata: ['reason' => $reason],
        );
    }
}
