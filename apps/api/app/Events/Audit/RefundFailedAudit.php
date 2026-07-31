<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\RefundTransaction;

class RefundFailedAudit extends BusinessAuditEvent
{
    public static function fromRefund(RefundTransaction $refund, Admin $admin, ?string $reason = null): self
    {
        return self::make(
            type: ActivityEventType::RefundFailed,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: RefundTransaction::class,
            subjectId: $refund->id,
            description: sprintf('Refund of %s %s failed.', $refund->amount, $refund->currency),
            newValues: ['status' => 'failed', 'reason' => $reason],
            metadata: ['order_id' => $refund->order_id],
        );
    }
}
