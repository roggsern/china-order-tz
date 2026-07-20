<?php

namespace App\Providers;

use App\Services\China\ChinaWorkflowEngine;
use Illuminate\Support\ServiceProvider;

class ChinaWorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChinaWorkflowEngine::class);
    }
}
