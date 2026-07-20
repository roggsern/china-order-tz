<?php

namespace App\Providers;

use App\Events\Audit\GrowthPlatformAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Services\Growth\CampaignEngine;
use App\Services\Growth\GrowthEngine;
use App\Services\Growth\JourneyEngine;
use App\Services\Growth\SegmentEngine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class GrowthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SegmentEngine::class);
        $this->app->singleton(CampaignEngine::class);
        $this->app->singleton(JourneyEngine::class);
        $this->app->singleton(GrowthEngine::class);
    }

    public function boot(): void
    {
        Event::listen(GrowthPlatformAudit::class, [RecordActivityLog::class, 'record']);
    }
}
