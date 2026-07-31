<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\WarehousePickList;

class PickCompletedAudit extends BusinessAuditEvent
{
    public static function fromPickList(WarehousePickList $pickList, Admin $admin): self
    {
        return self::make(
            type: ActivityEventType::PickCompleted,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: WarehousePickList::class,
            subjectId: $pickList->id,
            description: sprintf('Pick list completed for order warehouse job %s.', $pickList->warehouse_job_id),
            newValues: ['status' => 'completed'],
            metadata: [
                'order_id' => $pickList->order_id,
                'warehouse_job_id' => $pickList->warehouse_job_id,
            ],
        );
    }
}
