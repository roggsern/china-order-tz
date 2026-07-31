<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\ChinaProcurementRequirement;

class ChinaPurchaseCompletedAudit extends BusinessAuditEvent
{
    public static function fromRequirement(ChinaProcurementRequirement $requirement, Admin $admin): self
    {
        $requirement->loadMissing('product', 'variant');

        return self::make(
            type: ActivityEventType::ChinaPurchaseCompleted,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: ChinaProcurementRequirement::class,
            subjectId: $requirement->id,
            description: sprintf(
                'China procurement requirement completed for %s.',
                $requirement->product?->name ?? 'product',
            ),
            newValues: [
                'quantity_required' => $requirement->quantity_required,
                'quantity_purchased' => $requirement->quantity_purchased,
                'status' => $requirement->status instanceof \BackedEnum ? $requirement->status->value : $requirement->status,
            ],
        );
    }
}
