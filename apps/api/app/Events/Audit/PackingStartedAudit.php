<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\WarehousePackingRecord;

class PackingStartedAudit extends BusinessAuditEvent
{
    public static function fromPackingRecord(WarehousePackingRecord $record, Admin $admin): self
    {
        return self::make(
            type: ActivityEventType::PackingStarted,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: WarehousePackingRecord::class,
            subjectId: $record->id,
            description: sprintf('Packing started for warehouse job %s.', $record->warehouse_job_id),
            newValues: ['status' => 'in_progress'],
            metadata: ['warehouse_job_id' => $record->warehouse_job_id],
        );
    }
}
