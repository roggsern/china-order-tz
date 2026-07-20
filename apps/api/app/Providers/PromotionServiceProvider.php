<?php

namespace App\Providers;

use App\Events\Audit\PromotionActivatedAudit;
use App\Events\Audit\PromotionCreatedAudit;
use App\Events\Audit\PromotionExpiredAudit;
use App\Events\Audit\PromotionUpdatedAudit;
use App\Events\Audit\PromotionUsedAudit;
use App\Events\Promotions\PromotionActivated;
use App\Events\Promotions\PromotionCreated;
use App\Events\Promotions\PromotionExpired;
use App\Events\Promotions\PromotionUpdated;
use App\Events\Promotions\PromotionUsed;
use App\Listeners\Audit\RecordActivityLog;
use App\Listeners\Promotions\HandlePromotionLifecycle;
use App\Services\Promotions\DiscountResolver;
use App\Services\Promotions\PromotionEligibilityService;
use App\Services\Promotions\PromotionEngine;
use App\Services\Promotions\PromotionUsageService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PromotionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PromotionUsageService::class);
        $this->app->singleton(PromotionEligibilityService::class);
        $this->app->singleton(DiscountResolver::class);
        $this->app->singleton(PromotionEngine::class);
    }

    public function boot(): void
    {
        Event::listen(PromotionCreated::class, [HandlePromotionLifecycle::class, 'onCreated']);
        Event::listen(PromotionUpdated::class, [HandlePromotionLifecycle::class, 'onUpdated']);
        Event::listen(PromotionActivated::class, [HandlePromotionLifecycle::class, 'onActivated']);
        Event::listen(PromotionUsed::class, [HandlePromotionLifecycle::class, 'onUsed']);
        Event::listen(PromotionExpired::class, [HandlePromotionLifecycle::class, 'onExpired']);

        foreach ([
            PromotionCreatedAudit::class,
            PromotionUpdatedAudit::class,
            PromotionActivatedAudit::class,
            PromotionUsedAudit::class,
            PromotionExpiredAudit::class,
        ] as $auditEvent) {
            Event::listen($auditEvent, [RecordActivityLog::class, 'record']);
        }
    }
}
