<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionUsage;

class PromotionUsedAudit extends BusinessAuditEvent
{
    public static function fromUsage(Promotion $promotion, PromotionUsage $usage, Order $order): self
    {
        return self::make(
            type: ActivityEventType::PromotionUsed,
            actorType: ActivityActorType::System,
            actorId: null,
            subjectType: Promotion::class,
            subjectId: $promotion->id,
            description: sprintf('Promotion %s used on order %s', $promotion->code ?? $promotion->name, $order->order_number),
            newValues: [
                'order_id' => $order->id,
                'discount_amount' => $usage->discount_amount,
                'customer_id' => $usage->customer_id,
            ],
        );
    }
}
