<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\PurchaseOrder;

class PurchaseOrderConfirmedAudit extends BusinessAuditEvent
{
    public static function fromOrder(PurchaseOrder $order, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PurchaseOrderConfirmed,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: PurchaseOrder::class,
            subjectId: $order->id,
            description: sprintf('Purchase order %s was confirmed.', $order->purchase_number),
            newValues: [
                'purchase_number' => $order->purchase_number,
                'status' => 'confirmed',
                'confirmed_at' => optional($order->confirmed_at)?->toIso8601String(),
            ],
        );
    }
}
