<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Fulfillment;

class CompanyShippingHandoverCompleted extends BusinessAuditEvent
{
    public static function record(
        Admin $admin,
        string $fulfillmentId,
        string $method,
    ): self {
        return self::make(
            type: ActivityEventType::CompanyShippingHandoverCompleted,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: Fulfillment::class,
            subjectId: $fulfillmentId,
            description: sprintf(
                'China company shipping handover completed (%s).',
                str_replace('_', ' ', $method),
            ),
            oldValues: null,
            newValues: [
                'fulfillment_id' => $fulfillmentId,
                'method' => $method,
                'admin_id' => $admin->id,
            ],
            metadata: [
                'fulfillment_id' => $fulfillmentId,
                'method' => $method,
                'admin_id' => $admin->id,
            ],
            action: 'completed',
        );
    }
}
