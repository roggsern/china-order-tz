<?php

namespace App\Providers;

use App\Events\Audit\FulfillmentAssignedAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Fulfillment\FulfillmentStatusHistoryRecorder;
use App\Services\Fulfillment\FulfillmentStrategyResolver;
use App\Services\Fulfillment\Strategies\ChinaFulfillmentStrategy;
use App\Services\Fulfillment\Strategies\LocalFulfillmentStrategy;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class FulfillmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FulfillmentStrategyResolver::class);
        $this->app->singleton(LocalFulfillmentStrategy::class);
        $this->app->singleton(ChinaFulfillmentStrategy::class);

        $this->app->singleton(FulfillmentEngine::class, function ($app) {
            return new FulfillmentEngine(
                [
                    $app->make(LocalFulfillmentStrategy::class),
                    $app->make(ChinaFulfillmentStrategy::class),
                ],
                $app->make(OrderLifecycleEngine::class),
                $app->make(FulfillmentStatusHistoryRecorder::class),
            );
        });
    }

    public function boot(): void
    {
        Event::listen(FulfillmentAssignedAudit::class, [RecordActivityLog::class, 'record']);
    }
}
