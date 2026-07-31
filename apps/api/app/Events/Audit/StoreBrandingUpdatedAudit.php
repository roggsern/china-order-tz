<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Store;

class StoreBrandingUpdatedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function fromChange(
        Store $store,
        array $before,
        array $after,
        ?Admin $admin = null,
    ): self {
        return self::make(
            type: ActivityEventType::StoreBrandingUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Store::class,
            subjectId: $store->id,
            description: sprintf('Store branding updated: %s', $store->name),
            oldValues: $before,
            newValues: $after,
            metadata: [
                'store_id' => $store->id,
                'store_code' => $store->code,
                'store_name' => $store->name,
            ],
        );
    }
}
