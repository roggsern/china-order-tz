<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Services\Audit\Contracts\AuditableEvent;
use Illuminate\Support\Facades\Event;

/**
 * Public facade for the Audit Platform.
 * Business modules publish Laravel events — they never write logs directly.
 */
class AuditPlatform
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly ActivityLogFormatter $formatter,
    ) {}

    /**
     * Dispatch a business event. The Audit Listener records the activity log.
     */
    public function publish(AuditableEvent $event): void
    {
        Event::dispatch($event);
    }

    /**
     * Persist an auditable event (used by the Audit Listener only).
     */
    public function record(AuditableEvent $event): ActivityLog
    {
        return $this->logger->write($event);
    }

    public function formatter(): ActivityLogFormatter
    {
        return $this->formatter;
    }
}
