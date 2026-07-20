<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;

class AnalyticsReportExportedAudit extends BusinessAuditEvent
{
    public static function exported(Admin $admin, string $type, string $format, array $filters = []): self
    {
        return self::make(
            type: ActivityEventType::AnalyticsReportExported,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: null,
            subjectId: null,
            description: sprintf('Analytics report exported: %s (%s)', $type, $format),
            newValues: [
                'type' => $type,
                'format' => $format,
            ],
            metadata: [
                'filters' => $filters,
            ],
            action: 'exported',
        );
    }
}
