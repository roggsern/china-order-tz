<?php

namespace App\Providers;

use App\Events\Audit\InventoryControlAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Services\Inventory\ChinaInventoryPipeline;
use App\Services\Inventory\ChinaInventoryStockReporter;
use App\Services\Inventory\InventoryCommitmentService;
use App\Services\Inventory\InventoryControlEngine;
use App\Services\Inventory\InventoryMutationGate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class InventoryControlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InventoryMutationGate::class);
        $this->app->singleton(InventoryCommitmentService::class);
        $this->app->singleton(InventoryControlEngine::class);
        $this->app->singleton(ChinaInventoryPipeline::class);
        $this->app->singleton(ChinaInventoryStockReporter::class);
    }

    public function boot(): void
    {
        Event::listen(InventoryControlAudit::class, [RecordActivityLog::class, 'record']);
    }
}
