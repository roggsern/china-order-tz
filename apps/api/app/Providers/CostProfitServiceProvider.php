<?php

namespace App\Providers;

use App\Events\Audit\CostUpdatedAudit;
use App\Events\Audit\OrderCostCapturedAudit;
use App\Events\Audit\ProfitCalculatedAudit;
use App\Events\CostProfit\CostUpdated;
use App\Events\CostProfit\LowMarginDetected;
use App\Events\CostProfit\OrderCostCaptured;
use App\Events\CostProfit\ProfitCalculated;
use App\Listeners\Audit\RecordActivityLog;
use App\Listeners\CostProfit\HandleCostProfitLifecycle;
use App\Services\CostProfit\CostEngine;
use App\Services\CostProfit\CostSnapshotService;
use App\Services\CostProfit\ProfitEngine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CostProfitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CostSnapshotService::class);
        $this->app->singleton(CostEngine::class);
        $this->app->singleton(ProfitEngine::class);
    }

    public function boot(): void
    {
        Event::listen(OrderCostCaptured::class, [HandleCostProfitLifecycle::class, 'onOrderCostCaptured']);
        Event::listen(CostUpdated::class, [HandleCostProfitLifecycle::class, 'onCostUpdated']);
        Event::listen(ProfitCalculated::class, [HandleCostProfitLifecycle::class, 'onProfitCalculated']);
        Event::listen(LowMarginDetected::class, [HandleCostProfitLifecycle::class, 'onLowMargin']);

        foreach ([
            OrderCostCapturedAudit::class,
            CostUpdatedAudit::class,
            ProfitCalculatedAudit::class,
        ] as $auditEvent) {
            Event::listen($auditEvent, [RecordActivityLog::class, 'record']);
        }
    }
}
