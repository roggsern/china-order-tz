<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\ChinaProcurementRequirement;
use App\Models\Order;

class ChinaPurchaseRequirementCreatedAudit extends BusinessAuditEvent
{
    public static function fromRequirement(ChinaProcurementRequirement $requirement, Order $order): self
    {
        $requirement->loadMissing('product', 'variant');

        return self::make(
            type: ActivityEventType::ChinaPurchaseRequirementCreated,
            actorType: ActivityActorType::System,
            actorId: null,
            subjectType: ChinaProcurementRequirement::class,
            subjectId: $requirement->id,
            description: sprintf(
                'China procurement requirement updated for %s (order %s).',
                $requirement->product?->name ?? 'product',
                $order->order_number,
            ),
            newValues: [
                'product_id' => $requirement->product_id,
                'product_variant_id' => $requirement->product_variant_id,
                'quantity_required' => $requirement->quantity_required,
                'status' => $requirement->status instanceof \BackedEnum ? $requirement->status->value : $requirement->status,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        );
    }
}
