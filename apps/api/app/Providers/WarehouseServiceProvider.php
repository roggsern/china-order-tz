<?php

namespace App\Providers;

use App\Services\Warehouse\WarehouseEngine;
use App\Services\Warehouse\WarehouseJobNumberGenerator;
use Illuminate\Support\ServiceProvider;

class WarehouseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WarehouseJobNumberGenerator::class);
        $this->app->singleton(WarehouseEngine::class);
    }
}
