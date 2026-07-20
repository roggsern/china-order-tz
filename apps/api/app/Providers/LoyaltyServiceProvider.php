<?php

namespace App\Providers;

use App\Events\Audit\LoyaltyPlatformAudit;
use App\Events\Audit\PaymentConfirmed;
use App\Listeners\Audit\RecordActivityLog;
use App\Listeners\Loyalty\HandleLoyaltyLifecycle;
use App\Services\Loyalty\LoyaltyEngine;
use App\Services\Loyalty\LoyaltyNumberGenerator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LoyaltyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoyaltyNumberGenerator::class);
        $this->app->singleton(LoyaltyEngine::class);
    }

    public function boot(): void
    {
        Event::listen(PaymentConfirmed::class, [HandleLoyaltyLifecycle::class, 'onPaymentConfirmed']);

        Event::listen(LoyaltyPlatformAudit::class, [RecordActivityLog::class, 'record']);
    }
}
