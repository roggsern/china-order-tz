<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\RefundTransaction;

class RefundProcessedAudit extends BusinessAuditEvent
{
    public static function fromRefund(RefundTransaction $refund, Admin $admin): self
    {
        return self::make(
            type: ActivityEventType::RefundProcessed,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: RefundTransaction::class,
            subjectId: $refund->id,
            description: sprintf('Refund of %s %s entered processing.', $refund->amount, $refund->currency),
            newValues: ['status' => 'processing'],
            metadata: ['order_id' => $refund->order_id],
        );
    }
}
