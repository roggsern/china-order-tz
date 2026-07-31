<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;

class FeatureConfigurationUpdatedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function fromChange(array $before, array $after, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::FeatureConfigurationUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: 'feature_configuration',
            subjectId: 'features',
            description: 'Feature configuration was updated.',
            oldValues: $before,
            newValues: $after,
            metadata: [
                'group' => 'features',
            ],
        );
    }
}
