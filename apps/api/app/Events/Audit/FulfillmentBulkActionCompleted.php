<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;

class FulfillmentBulkActionCompleted extends BusinessAuditEvent
{
    public static function record(
        Admin $admin,
        string $batchId,
        string $actionKey,
        int $requestedCount,
        int $succeededCount,
        int $failedCount,
        int $skippedCount,
    ): self {
        return self::make(
            type: ActivityEventType::FulfillmentBulkActionCompleted,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: Admin::class,
            subjectId: $admin->id,
            description: sprintf(
                'Bulk fulfilment action %s completed for %d orders (%d succeeded, %d failed, %d skipped).',
                $actionKey,
                $requestedCount,
                $succeededCount,
                $failedCount,
                $skippedCount,
            ),
            oldValues: null,
            newValues: [
                'action_key' => $actionKey,
                'requested_count' => $requestedCount,
                'succeeded_count' => $succeededCount,
                'failed_count' => $failedCount,
                'skipped_count' => $skippedCount,
            ],
            metadata: [
                'batch_id' => $batchId,
                'action_key' => $actionKey,
            ],
            action: 'bulk_completed',
        );
    }
}
