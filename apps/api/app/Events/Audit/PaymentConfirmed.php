<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Order;

class PaymentConfirmed extends BusinessAuditEvent
{
    public static function fromOrder(Order $order): self
    {
        return self::make(
            type: ActivityEventType::PaymentConfirmed,
            actorType: ActivityActorType::System,
            actorId: null,
            subjectType: Order::class,
            subjectId: $order->id,
            description: sprintf('Payment confirmed for order %s.', $order->order_number),
            oldValues: ['status' => 'pending_payment'],
            newValues: [
                'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
                'paid_at' => optional($order->paid_at)?->toIso8601String(),
                'total' => (string) $order->total,
            ],
            metadata: [
                'order_number' => $order->order_number,
            ],
        );
    }
}
