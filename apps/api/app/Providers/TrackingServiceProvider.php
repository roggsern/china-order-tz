<?php

namespace App\Providers;

use App\Services\Tracking\OrderTimelineComposer;
use App\Services\Tracking\ShipmentStatusResolver;
use App\Services\Tracking\TrackingEngine;
use App\Services\Tracking\TrackingTimelineBuilder;
use Illuminate\Support\ServiceProvider;

class TrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShipmentStatusResolver::class);
        $this->app->singleton(TrackingTimelineBuilder::class);
        $this->app->singleton(OrderTimelineComposer::class);
        $this->app->singleton(TrackingEngine::class);
    }
}
