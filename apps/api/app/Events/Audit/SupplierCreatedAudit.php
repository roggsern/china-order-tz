<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Supplier;

class SupplierCreatedAudit extends BusinessAuditEvent
{
    public static function fromSupplier(Supplier $supplier, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::SupplierCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Supplier::class,
            subjectId: $supplier->id,
            description: sprintf('Supplier %s (%s) was created.', $supplier->name, $supplier->code),
            newValues: [
                'name' => $supplier->name,
                'code' => $supplier->code,
                'country' => $supplier->country,
            ],
        );
    }
}
