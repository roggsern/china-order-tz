<?php

namespace App\Providers;

use App\Events\Audit\AnalyticsReportExportedAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Services\Analytics\ChinaCommercialAnalyticsService;
use App\Services\Analytics\RetailAnalyticsEngine;
use App\Services\Reporting\CommandCenterDashboardService;
use App\Services\Reporting\ExportService;
use App\Services\Reporting\GrowthInsightService;
use App\Services\Reporting\MetricsEngine;
use App\Services\Reporting\ReportGenerator;
use App\Services\Reporting\ReportingEngine;
use App\Services\Storefront\StorefrontAnalyticsService;
use App\Services\Storefront\StorefrontConversionAnalyticsService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetricsEngine::class);
        $this->app->singleton(CommandCenterDashboardService::class);
        $this->app->singleton(ReportGenerator::class);
        $this->app->singleton(ExportService::class);
        $this->app->singleton(ReportingEngine::class);
        $this->app->singleton(RetailAnalyticsEngine::class);
        $this->app->singleton(ChinaCommercialAnalyticsService::class);
        $this->app->singleton(StorefrontAnalyticsService::class);
        $this->app->singleton(StorefrontConversionAnalyticsService::class);
        $this->app->singleton(GrowthInsightService::class);
    }

    public function boot(): void
    {
        Event::listen(AnalyticsReportExportedAudit::class, [RecordActivityLog::class, 'record']);
    }
}
