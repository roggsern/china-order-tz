<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\ReturnRequest;

class ReturnRequestedAudit extends BusinessAuditEvent
{
    public static function fromReturn(ReturnRequest $return): self
    {
        $return->loadMissing('order');

        return self::make(
            type: ActivityEventType::ReturnRequested,
            actorType: ActivityActorType::Customer,
            actorId: $return->customer_id,
            subjectType: ReturnRequest::class,
            subjectId: $return->id,
            description: sprintf(
                'Return requested for order %s.',
                $return->order?->order_number ?? $return->order_id,
            ),
            newValues: [
                'status' => $return->status instanceof \BackedEnum ? $return->status->value : $return->status,
                'reason' => $return->reason,
            ],
            metadata: ['order_id' => $return->order_id],
        );
    }
}
