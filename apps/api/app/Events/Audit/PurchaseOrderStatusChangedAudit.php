<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Enums\PurchaseOrderStatus;
use App\Models\Admin;
use App\Models\PurchaseOrder;

class PurchaseOrderStatusChangedAudit extends BusinessAuditEvent
{
    public static function fromTransition(
        PurchaseOrder $order,
        PurchaseOrderStatus $from,
        PurchaseOrderStatus $to,
        ?Admin $admin = null,
    ): self {
        return self::make(
            type: ActivityEventType::PurchaseOrderStatusChanged,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: PurchaseOrder::class,
            subjectId: $order->id,
            description: sprintf(
                'Purchase order %s status changed from %s to %s.',
                $order->purchase_number,
                $from->value,
                $to->value,
            ),
            oldValues: ['status' => $from->value],
            newValues: ['status' => $to->value],
        );
    }
}
