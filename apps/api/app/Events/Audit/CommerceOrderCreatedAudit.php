<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Order;
use App\Models\User;

class CommerceOrderCreatedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $channelSnapshot
     */
    public static function fromOrder(Order $order, array $channelSnapshot, ?User $customer = null): self
    {
        $customer ??= $order->user;

        return self::make(
            type: ActivityEventType::CommerceOrderCreated,
            actorType: $customer ? ActivityActorType::Customer : ActivityActorType::System,
            actorId: $customer?->id,
            subjectType: Order::class,
            subjectId: $order->id,
            description: sprintf(
                'Order %s created on commerce channel %s.',
                $order->order_number,
                $channelSnapshot['code'] ?? 'unknown',
            ),
            newValues: [
                'order_number' => $order->order_number,
                'commerce_channel_code' => $channelSnapshot['code'] ?? null,
                'commerce_channel_snapshot' => $channelSnapshot,
            ],
            metadata: [
                'commerce_channel_id' => $order->commerce_channel_id,
            ],
        );
    }
}
