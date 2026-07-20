<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\ReturnRequest;

class ReturnCompletedAudit extends BusinessAuditEvent
{
    public static function fromReturn(ReturnRequest $return, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::ReturnCompleted,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: ReturnRequest::class,
            subjectId: $return->id,
            description: sprintf('Return %s was completed.', $return->id),
            newValues: ['status' => 'completed'],
            metadata: ['order_id' => $return->order_id],
        );
    }
}
