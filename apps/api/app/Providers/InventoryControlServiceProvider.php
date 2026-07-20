<?php

namespace App\Providers;

use App\Events\Audit\InventoryControlAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Services\Inventory\InventoryControlEngine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class InventoryControlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InventoryControlEngine::class);
    }

    public function boot(): void
    {
        Event::listen(InventoryControlAudit::class, [RecordActivityLog::class, 'record']);
    }
}
