<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Services\Audit\Contracts\AuditableEvent;

/**
 * Persist append-only activity log rows. Only called from the Audit Platform.
 */
class ActivityLogger
{
    public function write(AuditableEvent $event): ActivityLog
    {
        return ActivityLog::query()->create([
            'event_type' => $event->eventType()->value,
            'action' => $event->action(),
            'actor_type' => $event->actorType()->value,
            'actor_id' => $event->actorId(),
            'subject_type' => $event->subjectType(),
            'subject_id' => $event->subjectId(),
            'description' => $event->description(),
            'old_values' => $event->oldValues(),
            'new_values' => $event->newValues(),
            'metadata' => $event->metadata(),
            'ip_address' => $event->ipAddress(),
            'user_agent' => $event->userAgent(),
            'created_at' => now(),
        ]);
    }
}
