<?php

namespace App\Providers;

use App\Services\ProductShipping\ProductShippingOptionEngine;
use Illuminate\Support\ServiceProvider;

class ProductShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProductShippingOptionEngine::class);
    }
}
