<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\WarehouseJob;

class WarehouseJobCreated extends BusinessAuditEvent
{
    public static function fromJob(WarehouseJob $job): self
    {
        return self::make(
            type: ActivityEventType::WarehouseJobCreated,
            actorType: ActivityActorType::System,
            actorId: null,
            subjectType: WarehouseJob::class,
            subjectId: $job->id,
            description: sprintf('Warehouse job %s was created.', $job->job_number),
            newValues: [
                'job_number' => $job->job_number,
                'status' => $job->status instanceof \BackedEnum ? $job->status->value : $job->status,
                'order_id' => $job->order_id,
                'fulfillment_id' => $job->fulfillment_id,
            ],
        );
    }
}
