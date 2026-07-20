<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\RefundTransaction;

class RefundCompletedAudit extends BusinessAuditEvent
{
    public static function fromRefund(RefundTransaction $refund, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::RefundCompleted,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: RefundTransaction::class,
            subjectId: $refund->id,
            description: sprintf('Refund of %s %s was completed.', $refund->amount, $refund->currency),
            oldValues: ['status' => 'processing'],
            newValues: ['status' => 'completed', 'amount' => (string) $refund->amount],
            metadata: [
                'return_request_id' => $refund->return_request_id,
                'order_id' => $refund->order_id,
            ],
        );
    }
}
