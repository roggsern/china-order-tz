<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\ReturnRequest;

class ReturnRejectedAudit extends BusinessAuditEvent
{
    public static function fromReturn(ReturnRequest $return, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::ReturnRejected,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: ReturnRequest::class,
            subjectId: $return->id,
            description: sprintf('Return %s was rejected.', $return->id),
            oldValues: ['status' => 'requested'],
            newValues: ['status' => 'rejected'],
            metadata: ['order_id' => $return->order_id, 'rejected_by' => $admin?->id],
        );
    }
}
