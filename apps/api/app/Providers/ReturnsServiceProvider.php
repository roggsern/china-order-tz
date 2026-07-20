<?php

namespace App\Providers;

use App\Events\Audit\RefundCompletedAudit;
use App\Events\Audit\RefundCreatedAudit;
use App\Events\Audit\ReturnApprovedAudit;
use App\Events\Audit\ReturnCompletedAudit;
use App\Events\Audit\ReturnRefundAmountChanged;
use App\Events\Audit\ReturnRejectedAudit;
use App\Events\Audit\ReturnRequestedAudit;
use App\Events\Returns\RefundCompleted;
use App\Events\Returns\RefundCreated;
use App\Events\Returns\ReturnApproved;
use App\Events\Returns\ReturnCompleted;
use App\Events\Returns\ReturnRejected;
use App\Events\Returns\ReturnRequested;
use App\Listeners\Audit\RecordActivityLog;
use App\Listeners\Returns\HandleReturnLifecycle;
use App\Services\Returns\RefundEngine;
use App\Services\Returns\ReturnEligibilityService;
use App\Services\Returns\ReturnEngine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ReturnsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReturnEligibilityService::class);
        $this->app->singleton(ReturnEngine::class);
        $this->app->singleton(RefundEngine::class);
    }

    public function boot(): void
    {
        Event::listen(ReturnRequested::class, [HandleReturnLifecycle::class, 'onRequested']);
        Event::listen(ReturnApproved::class, [HandleReturnLifecycle::class, 'onApproved']);
        Event::listen(ReturnRejected::class, [HandleReturnLifecycle::class, 'onRejected']);
        Event::listen(ReturnCompleted::class, [HandleReturnLifecycle::class, 'onCompleted']);
        Event::listen(RefundCreated::class, [HandleReturnLifecycle::class, 'onRefundCreated']);
        Event::listen(RefundCompleted::class, [HandleReturnLifecycle::class, 'onRefundCompleted']);

        foreach ([
            ReturnRequestedAudit::class,
            ReturnApprovedAudit::class,
            ReturnRejectedAudit::class,
            ReturnCompletedAudit::class,
            ReturnRefundAmountChanged::class,
            RefundCreatedAudit::class,
            RefundCompletedAudit::class,
        ] as $auditEvent) {
            Event::listen($auditEvent, [RecordActivityLog::class, 'record']);
        }
    }
}
