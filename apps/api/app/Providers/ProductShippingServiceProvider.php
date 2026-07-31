<?php

namespace App\Providers;

use App\Events\Audit\ShippingRateUpdatedAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Services\ProductShipping\ProductShippingOptionEngine;
use App\Services\Shipping\ShippingRateService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ProductShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProductShippingOptionEngine::class);
        $this->app->singleton(ShippingRateService::class);
    }

    public function boot(): void
    {
        Event::listen(ShippingRateUpdatedAudit::class, [RecordActivityLog::class, 'record']);
    }
}
