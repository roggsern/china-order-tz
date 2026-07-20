<?php

namespace App\Providers;

use App\Services\Shipments\ShipmentEligibilityService;
use App\Services\Shipments\ShipmentEngine;
use App\Services\Shipments\ShipmentNumberGenerator;
use Illuminate\Support\ServiceProvider;

class ShipmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShipmentEligibilityService::class);
        $this->app->singleton(ShipmentNumberGenerator::class);
        $this->app->singleton(ShipmentEngine::class);
    }
}
