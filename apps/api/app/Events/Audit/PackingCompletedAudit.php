<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\WarehousePackingRecord;

class PackingCompletedAudit extends BusinessAuditEvent
{
    public static function fromPackingRecord(WarehousePackingRecord $record, Admin $admin): self
    {
        return self::make(
            type: ActivityEventType::PackingCompleted,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: WarehousePackingRecord::class,
            subjectId: $record->id,
            description: sprintf('Packing completed for warehouse job %s.', $record->warehouse_job_id),
            newValues: ['status' => 'completed', 'package_status' => $record->package_status],
            metadata: ['warehouse_job_id' => $record->warehouse_job_id],
        );
    }
}
