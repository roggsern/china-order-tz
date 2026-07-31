<?php

namespace App\Providers;

use App\Events\Audit\SupportMessageSentAudit;
use App\Events\Audit\SupportTicketAssignedAudit;
use App\Events\Audit\SupportTicketCreatedAudit;
use App\Events\Audit\SupportTicketStatusChangedAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Services\Support\SupportTicketEngine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SupportTicketEngine::class);
    }

    public function boot(): void
    {
        foreach ([
            SupportTicketCreatedAudit::class,
            SupportTicketAssignedAudit::class,
            SupportTicketStatusChangedAudit::class,
            SupportMessageSentAudit::class,
        ] as $auditEvent) {
            Event::listen($auditEvent, [RecordActivityLog::class, 'record']);
        }
    }
}
