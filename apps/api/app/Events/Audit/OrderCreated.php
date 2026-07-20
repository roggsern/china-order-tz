<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Order;
use App\Models\User;

class OrderCreated extends BusinessAuditEvent
{
    public static function fromOrder(Order $order, ?User $customer = null): self
    {
        $customer ??= $order->user;

        return self::make(
            type: ActivityEventType::OrderCreated,
            actorType: $customer ? ActivityActorType::Customer : ActivityActorType::System,
            actorId: $customer?->id,
            subjectType: Order::class,
            subjectId: $order->id,
            description: sprintf('Order %s was created.', $order->order_number),
            newValues: [
                'order_number' => $order->order_number,
                'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
                'total' => (string) $order->total,
                'currency' => $order->currency,
            ],
            metadata: [
                'checkout_session_id' => $order->checkout_session_id,
            ],
        );
    }
}
