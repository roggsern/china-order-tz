<?php

namespace App\Providers;

use App\Events\Audit\ReviewApprovedAudit;
use App\Events\Audit\ReviewRejectedAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Services\Reviews\ReviewModerationService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ReviewModerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReviewModerationService::class);
    }

    public function boot(): void
    {
        foreach ([
            ReviewApprovedAudit::class,
            ReviewRejectedAudit::class,
        ] as $auditEvent) {
            Event::listen($auditEvent, [RecordActivityLog::class, 'record']);
        }
    }
}
