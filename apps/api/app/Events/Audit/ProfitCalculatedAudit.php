<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\ProfitRecord;

class ProfitCalculatedAudit extends BusinessAuditEvent
{
    public static function fromRecord(ProfitRecord $record, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::ProfitCalculated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: ProfitRecord::class,
            subjectId: $record->id,
            description: sprintf(
                'Profit calculated for order — revenue %s, cost %s, margin %s%%.',
                $record->revenue,
                $record->total_cost,
                $record->margin_percentage,
            ),
            newValues: [
                'order_id' => $record->order_id,
                'revenue' => $record->revenue,
                'total_cost' => $record->total_cost,
                'gross_profit' => $record->gross_profit,
                'margin_percentage' => $record->margin_percentage,
                'currency' => $record->currency,
            ],
        );
    }
}
