<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\CustomerMetric;
use App\Models\CustomerProfile;

class CustomerMetricsRebuiltAudit extends BusinessAuditEvent
{
    public static function fromMetric(CustomerMetric $metric, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CustomerMetricsRebuilt,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CustomerProfile::class,
            subjectId: $metric->customer_profile_id,
            description: 'Customer metrics rebuilt',
            newValues: [
                'total_orders' => $metric->total_orders,
                'total_spend' => $metric->total_spend,
                'gross_profit_generated' => $metric->gross_profit_generated,
                'calculated_at' => optional($metric->calculated_at)?->toIso8601String(),
            ],
        );
    }
}
