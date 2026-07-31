<?php

namespace App\Providers;

use App\Events\Audit\PackingCompletedAudit;
use App\Events\Audit\PackingStartedAudit;
use App\Events\Audit\PickCompletedAudit;
use App\Events\Audit\PickStartedAudit;
use App\Events\Audit\TransferCompletedAudit;
use App\Events\Audit\TransferCreatedAudit;
use App\Events\Warehouse\PickCompleted;
use App\Events\Warehouse\PickStarted;
use App\Listeners\Audit\RecordActivityLog;
use App\Listeners\Warehouse\HandleWarehouseOperations;
use App\Services\Warehouse\WarehouseLocationService;
use App\Services\Warehouse\WarehousePackingService;
use App\Services\Warehouse\WarehousePickListService;
use App\Services\Warehouse\WarehouseTransferService;
use App\Services\Warehouse\WarehouseEngine;
use App\Services\Warehouse\WarehouseJobNumberGenerator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class WarehouseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WarehouseJobNumberGenerator::class);
        $this->app->singleton(WarehouseEngine::class);
        $this->app->singleton(WarehousePickListService::class);
        $this->app->singleton(WarehousePackingService::class);
        $this->app->singleton(WarehouseLocationService::class);
        $this->app->singleton(WarehouseTransferService::class);
    }

    public function boot(): void
    {
        Event::listen(PickStarted::class, [HandleWarehouseOperations::class, 'onPickStarted']);
        Event::listen(PickCompleted::class, [HandleWarehouseOperations::class, 'onPickCompleted']);

        foreach ([
            PickStartedAudit::class,
            PickCompletedAudit::class,
            PackingStartedAudit::class,
            PackingCompletedAudit::class,
            TransferCreatedAudit::class,
            TransferCompletedAudit::class,
        ] as $auditEvent) {
            Event::listen($auditEvent, [RecordActivityLog::class, 'record']);
        }
    }
}
