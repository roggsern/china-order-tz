<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Store;

class StoreStatusChangedAudit extends BusinessAuditEvent
{
    public static function fromChange(
        Store $store,
        bool $wasActive,
        bool $isActive,
        ?Admin $admin = null,
    ): self {
        return self::make(
            type: $isActive ? ActivityEventType::StoreActivated : ActivityEventType::StoreDeactivated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Store::class,
            subjectId: $store->id,
            description: sprintf(
                'Store %s: %s',
                $store->name,
                $isActive ? 'activated' : 'deactivated',
            ),
            oldValues: ['is_active' => $wasActive],
            newValues: ['is_active' => $isActive],
            metadata: [
                'store_id' => $store->id,
                'store_code' => $store->code,
                'store_name' => $store->name,
            ],
        );
    }
}
