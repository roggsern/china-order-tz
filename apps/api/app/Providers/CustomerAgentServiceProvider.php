<?php

namespace App\Providers;

use App\Services\CustomerAgent\CustomerAgentWorkflowEngine;
use Illuminate\Support\ServiceProvider;

class CustomerAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CustomerAgentWorkflowEngine::class);
    }
}
