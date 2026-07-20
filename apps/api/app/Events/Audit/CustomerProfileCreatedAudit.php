<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\CustomerProfile;

class CustomerProfileCreatedAudit extends BusinessAuditEvent
{
    public static function fromProfile(CustomerProfile $profile, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CustomerProfileCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CustomerProfile::class,
            subjectId: $profile->id,
            description: 'CRM customer profile created: '.$profile->customer_code,
            newValues: [
                'customer_code' => $profile->customer_code,
                'user_id' => $profile->user_id,
                'registration_source' => $profile->registration_source?->value,
            ],
        );
    }
}
