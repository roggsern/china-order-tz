<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Order;

class OrderCostCapturedAudit extends BusinessAuditEvent
{
    public static function fromOrder(Order $order, int $lines, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::OrderCostCaptured,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Order::class,
            subjectId: $order->id,
            description: sprintf(
                'Cost snapshots captured for order %s (%d line%s).',
                $order->order_number,
                $lines,
                $lines === 1 ? '' : 's',
            ),
            newValues: [
                'order_number' => $order->order_number,
                'lines' => $lines,
            ],
        );
    }
}
