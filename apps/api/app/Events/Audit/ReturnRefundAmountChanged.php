<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\RefundTransaction;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;

class ReturnRefundAmountChanged extends BusinessAuditEvent
{
    public static function fromChange(
        ?ReturnRequest $return,
        ?ReturnItem $item,
        ?string $oldAmount,
        string $newAmount,
        ?Admin $admin = null,
        ?RefundTransaction $refund = null,
    ): self {
        return self::make(
            type: ActivityEventType::ReturnRefundAmountChanged,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: $refund
                ? RefundTransaction::class
                : ($item ? ReturnItem::class : ReturnRequest::class),
            subjectId: $refund?->id ?? $item?->id ?? $return?->id,
            description: 'Refund amount was changed.',
            oldValues: ['refund_amount' => $oldAmount],
            newValues: ['refund_amount' => $newAmount],
            metadata: [
                'return_request_id' => $return?->id,
                'return_item_id' => $item?->id,
                'refund_transaction_id' => $refund?->id,
            ],
        );
    }
}
