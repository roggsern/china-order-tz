<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\PurchaseOrder;

class PurchaseOrderCreatedAudit extends BusinessAuditEvent
{
    public static function fromOrder(PurchaseOrder $order, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PurchaseOrderCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: PurchaseOrder::class,
            subjectId: $order->id,
            description: sprintf('Purchase order %s was created.', $order->purchase_number),
            newValues: [
                'purchase_number' => $order->purchase_number,
                'supplier_id' => $order->supplier_id,
                'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
            ],
        );
    }
}
