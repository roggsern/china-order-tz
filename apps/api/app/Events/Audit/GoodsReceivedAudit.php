<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\ReceivingRecord;

class GoodsReceivedAudit extends BusinessAuditEvent
{
    public static function fromReceiving(ReceivingRecord $record, ?Admin $admin = null): self
    {
        $record->loadMissing('purchaseOrder');

        return self::make(
            type: ActivityEventType::GoodsReceived,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: ReceivingRecord::class,
            subjectId: $record->id,
            description: sprintf(
                'Goods received for purchase order %s.',
                $record->purchaseOrder?->purchase_number ?? $record->purchase_order_id,
            ),
            newValues: [
                'purchase_order_id' => $record->purchase_order_id,
                'status' => $record->status instanceof \BackedEnum ? $record->status->value : $record->status,
                'received_at' => optional($record->received_at)?->toIso8601String(),
            ],
        );
    }
}
