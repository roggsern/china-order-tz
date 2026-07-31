<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\ChinaProcurementRequirement;

class ChinaPurchaseMarkedPurchasedAudit extends BusinessAuditEvent
{
    public static function fromRequirement(
        ChinaProcurementRequirement $requirement,
        Admin $admin,
        int $quantityPurchased,
    ): self {
        $requirement->loadMissing('product', 'variant');

        return self::make(
            type: ActivityEventType::ChinaPurchaseMarkedPurchased,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: ChinaProcurementRequirement::class,
            subjectId: $requirement->id,
            description: sprintf(
                'Marked %d units purchased for %s.',
                $quantityPurchased,
                $requirement->product?->name ?? 'product',
            ),
            newValues: [
                'quantity_purchased' => $requirement->quantity_purchased,
                'quantity_required' => $requirement->quantity_required,
                'status' => $requirement->status instanceof \BackedEnum ? $requirement->status->value : $requirement->status,
                'delta' => $quantityPurchased,
            ],
        );
    }
}
