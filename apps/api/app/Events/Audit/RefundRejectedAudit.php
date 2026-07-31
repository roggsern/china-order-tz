<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\RefundTransaction;

class RefundRejectedAudit extends BusinessAuditEvent
{
    public static function fromRefund(RefundTransaction $refund, Admin $admin, ?string $reason = null): self
    {
        return self::make(
            type: ActivityEventType::RefundRejected,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: RefundTransaction::class,
            subjectId: $refund->id,
            description: sprintf('Refund of %s %s was rejected.', $refund->amount, $refund->currency),
            newValues: ['status' => 'rejected', 'reason' => $reason],
            metadata: ['order_id' => $refund->order_id],
        );
    }
}
