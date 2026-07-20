<?php

namespace App\Listeners\Audit;

use App\Services\Audit\AuditPlatform;
use App\Services\Audit\Contracts\AuditableEvent;
use Illuminate\Support\Facades\Log;

/**
 * Listens to auditable business events and records immutable activity logs.
 *
 * Method is named record (not handle) so Laravel event discovery does not
 * also register this listener on AuditableEvent and double-write rows.
 */
class RecordActivityLog
{
    public function __construct(
        private readonly AuditPlatform $audit,
    ) {}

    public function record(AuditableEvent $event): void
    {
        try {
            $this->audit->record($event);
        } catch (\Throwable $e) {
            Log::error('audit.record_failed', [
                'event' => $event->eventType()->value,
                'message' => $e->getMessage(),
            ]);
        }
    }
}