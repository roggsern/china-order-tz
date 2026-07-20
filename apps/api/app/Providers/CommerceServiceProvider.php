<?php

namespace App\Providers;

use App\Events\Audit\CommerceChannelAssignedAudit;
use App\Events\Audit\CommerceOrderCreatedAudit;
use App\Events\Commerce\CommerceChannelAssigned;
use App\Events\Commerce\CommerceOrderCreated;
use App\Listeners\Audit\RecordActivityLog;
use App\Listeners\Commerce\HandleCommerceLifecycle;
use App\Services\Commerce\CommerceChannelResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommerceChannelResolver::class);
    }

    public function boot(): void
    {
        Event::listen(CommerceChannelAssigned::class, [HandleCommerceLifecycle::class, 'onChannelAssigned']);
        Event::listen(CommerceOrderCreated::class, [HandleCommerceLifecycle::class, 'onOrderCreated']);

        foreach ([
            CommerceChannelAssignedAudit::class,
            CommerceOrderCreatedAudit::class,
        ] as $auditEvent) {
            Event::listen($auditEvent, [RecordActivityLog::class, 'record']);
        }
    }
}
