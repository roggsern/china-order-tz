<?php

namespace App\Providers;

use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use Illuminate\Support\ServiceProvider;

class OrderLifecycleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrderLifecycleEngine::class);
    }
}
