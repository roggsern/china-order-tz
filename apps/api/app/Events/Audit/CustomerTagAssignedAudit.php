<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\CustomerProfile;
use App\Models\CustomerTag;

class CustomerTagAssignedAudit extends BusinessAuditEvent
{
    public static function fromAssignment(CustomerProfile $profile, CustomerTag $tag, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CustomerTagAssigned,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CustomerProfile::class,
            subjectId: $profile->id,
            description: "Tag {$tag->name} assigned to {$profile->customer_code}",
            newValues: ['tag_id' => $tag->id, 'tag_slug' => $tag->slug],
        );
    }
}
