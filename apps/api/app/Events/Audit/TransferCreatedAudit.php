<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\WarehouseStockTransfer;

class TransferCreatedAudit extends BusinessAuditEvent
{
    public static function fromTransfer(WarehouseStockTransfer $transfer, Admin $admin): self
    {
        return self::make(
            type: ActivityEventType::TransferCreated,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: WarehouseStockTransfer::class,
            subjectId: $transfer->id,
            description: sprintf('Warehouse transfer %s requested.', $transfer->transfer_number),
            newValues: ['status' => 'requested'],
            metadata: [
                'from_facility_id' => $transfer->from_facility_id,
                'to_facility_id' => $transfer->to_facility_id,
            ],
        );
    }
}
