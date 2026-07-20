<?php

namespace App\Providers;

use App\Services\Orders\OrderSnapshotEngine;
use Illuminate\Support\ServiceProvider;

class OrderSnapshotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrderSnapshotEngine::class);
    }
}
