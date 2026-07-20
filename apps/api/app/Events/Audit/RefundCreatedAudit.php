<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\RefundTransaction;

class RefundCreatedAudit extends BusinessAuditEvent
{
    public static function fromRefund(RefundTransaction $refund, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::RefundCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: RefundTransaction::class,
            subjectId: $refund->id,
            description: sprintf('Refund of %s %s was created.', $refund->amount, $refund->currency),
            newValues: [
                'amount' => (string) $refund->amount,
                'status' => $refund->status instanceof \BackedEnum ? $refund->status->value : $refund->status,
                'method' => $refund->method,
            ],
            metadata: [
                'return_request_id' => $refund->return_request_id,
                'order_id' => $refund->order_id,
            ],
        );
    }
}
