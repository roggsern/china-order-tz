<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\ReceivingRecord;

class InventoryReceivedAudit extends BusinessAuditEvent
{
    public static function fromReceiving(ReceivingRecord $record, ?Admin $admin = null): self
    {
        $record->loadMissing(['purchaseOrder', 'items']);

        return self::make(
            type: ActivityEventType::InventoryReceived,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: ReceivingRecord::class,
            subjectId: $record->id,
            description: sprintf(
                'Inventory increased from receiving on PO %s.',
                $record->purchaseOrder?->purchase_number ?? $record->purchase_order_id,
            ),
            newValues: [
                'lines' => $record->items->count(),
                'purchase_order_id' => $record->purchase_order_id,
            ],
        );
    }
}
