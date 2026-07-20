<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Supplier;

class SupplierUpdatedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function fromChanges(Supplier $supplier, array $before, array $after, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::SupplierUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Supplier::class,
            subjectId: $supplier->id,
            description: sprintf('Supplier %s was updated.', $supplier->code ?? $supplier->name),
            oldValues: $before,
            newValues: $after,
        );
    }
}
