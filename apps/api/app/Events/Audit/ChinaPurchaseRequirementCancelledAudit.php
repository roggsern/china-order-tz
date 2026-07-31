<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Order;

class ChinaPurchaseRequirementCancelledAudit extends BusinessAuditEvent
{
    public static function fromOrder(Order $order, ?Admin $admin = null, int $releasedLinks = 0): self
    {
        return self::make(
            type: ActivityEventType::ChinaPurchaseRequirementCancelled,
            actorType: $admin !== null ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Order::class,
            subjectId: $order->id,
            description: sprintf(
                'China procurement demand reversed for cancelled order %s.',
                $order->order_number,
            ),
            metadata: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'released_links' => $releasedLinks,
            ],
        );
    }
}
