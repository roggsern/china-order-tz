<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Store;

class StoreCreatedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $after
     */
    public static function fromStore(Store $store, array $after, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::StoreCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Store::class,
            subjectId: $store->id,
            description: sprintf('Store created: %s', $store->name),
            oldValues: null,
            newValues: $after,
            metadata: [
                'store_id' => $store->id,
                'store_code' => $store->code,
                'store_name' => $store->name,
            ],
        );
    }
}
