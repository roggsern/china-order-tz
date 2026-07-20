<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\CustomerProfile;

class CustomerProfileUpdatedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $before
     */
    public static function fromProfile(CustomerProfile $profile, array $before, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CustomerProfileUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CustomerProfile::class,
            subjectId: $profile->id,
            description: 'CRM customer profile updated: '.$profile->customer_code,
            oldValues: $before,
            newValues: $profile->only(array_keys($before)),
        );
    }
}
