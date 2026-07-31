<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\RefundTransaction;

class RefundApprovedAudit extends BusinessAuditEvent
{
    public static function fromRefund(RefundTransaction $refund, Admin $admin, ?string $previousStatus = null): self
    {
        return self::make(
            type: ActivityEventType::RefundApproved,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: RefundTransaction::class,
            subjectId: $refund->id,
            description: sprintf('Refund of %s %s was approved.', $refund->amount, $refund->currency),
            oldValues: ['status' => $previousStatus],
            newValues: ['status' => $refund->status instanceof \BackedEnum ? $refund->status->value : $refund->status],
            metadata: ['order_id' => $refund->order_id],
        );
    }
}
