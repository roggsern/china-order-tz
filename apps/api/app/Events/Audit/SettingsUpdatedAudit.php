<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Setting;

class SettingsUpdatedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public static function fromChange(
        Setting $setting,
        ?array $oldValues,
        ?array $newValues,
        ?Admin $admin = null,
        ?array $metadata = null,
    ): self {
        return self::make(
            type: ActivityEventType::SettingsUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Setting::class,
            subjectId: $setting->id,
            description: sprintf('Setting "%s" was updated.', $setting->key),
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: array_merge([
                'key' => $setting->key,
                'group' => $setting->group,
            ], $metadata ?? []),
        );
    }
}
