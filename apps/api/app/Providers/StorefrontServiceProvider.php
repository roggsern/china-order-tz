<?php

namespace App\Providers;

use App\Events\Audit\PaymentConfirmed;
use App\Listeners\Storefront\HandleStorefrontLifecycle;
use App\Services\Storefront\StorefrontEventService;
use App\Services\Storefront\VisitorIdentityService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class StorefrontServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VisitorIdentityService::class);
        $this->app->singleton(StorefrontEventService::class);
    }

    public function boot(): void
    {
        Event::listen(PaymentConfirmed::class, [HandleStorefrontLifecycle::class, 'onPaymentConfirmed']);
    }
}
