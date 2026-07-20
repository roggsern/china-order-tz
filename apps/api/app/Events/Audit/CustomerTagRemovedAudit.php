<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\CustomerProfile;
use App\Models\CustomerTag;

class CustomerTagRemovedAudit extends BusinessAuditEvent
{
    public static function fromRemoval(CustomerProfile $profile, CustomerTag $tag, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CustomerTagRemoved,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CustomerProfile::class,
            subjectId: $profile->id,
            description: "Tag {$tag->name} removed from {$profile->customer_code}",
            oldValues: ['tag_id' => $tag->id, 'tag_slug' => $tag->slug],
        );
    }
}
