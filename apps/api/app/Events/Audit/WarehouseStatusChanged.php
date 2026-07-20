<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Models\WarehouseJob;

class WarehouseStatusChanged extends BusinessAuditEvent
{
    public static function fromTransition(
        WarehouseJob $job,
        WarehouseJobStatus|string $from,
        WarehouseJobStatus|string $to,
        ?Admin $admin = null,
    ): self {
        $fromValue = $from instanceof WarehouseJobStatus ? $from->value : (string) $from;
        $toValue = $to instanceof WarehouseJobStatus ? $to->value : (string) $to;

        return self::make(
            type: ActivityEventType::WarehouseStatusChanged,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: WarehouseJob::class,
            subjectId: $job->id,
            description: sprintf(
                'Warehouse job %s status changed from %s to %s.',
                $job->job_number,
                $fromValue,
                $toValue,
            ),
            oldValues: ['status' => $fromValue],
            newValues: ['status' => $toValue],
            metadata: [
                'order_id' => $job->order_id,
                'job_number' => $job->job_number,
            ],
        );
    }
}
