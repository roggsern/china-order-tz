<?php

namespace App\Providers;

use App\Services\Delivery\DeliveryOptionEngine;
use App\Services\Delivery\DeliveryOptionValidator;
use App\Services\Delivery\DeliveryTypeResolver;
use Illuminate\Support\ServiceProvider;

class DeliveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DeliveryTypeResolver::class);
        $this->app->singleton(DeliveryOptionValidator::class);
        $this->app->singleton(DeliveryOptionEngine::class);
    }
}
