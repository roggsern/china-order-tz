<?php

namespace App\Providers;

use App\Events\Audit\FeatureConfigurationUpdatedAudit;
use App\Events\Audit\SettingsUpdatedAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Services\ConfigurationHealth\Checks\FeatureHealthCheck;
use App\Services\ConfigurationHealth\Checks\NotificationHealthCheck;
use App\Services\ConfigurationHealth\Checks\PaymentHealthCheck;
use App\Services\ConfigurationHealth\Checks\SecurityHealthCheck;
use App\Services\ConfigurationHealth\Checks\ShippingHealthCheck;
use App\Services\ConfigurationHealth\Checks\StoreHealthCheck;
use App\Services\ConfigurationHealth\ConfigurationHealthService;
use App\Services\Features\FeatureAvailabilityService;
use App\Services\Features\FeatureConfigurationService;
use App\Services\Features\FeatureFlagResolver;
use App\Services\Features\MaintenanceModeResolver;
use App\Services\Settings\ConfigurationDashboardService;
use App\Services\Settings\SettingsAuditQueryService;
use App\Services\Settings\SettingsAuditService;
use App\Services\Settings\SettingsCache;
use App\Services\Settings\SettingsRepository;
use App\Services\Settings\SettingsService;
use App\Services\Settings\SettingsValueCaster;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsValueCaster::class);
        $this->app->singleton(SettingsCache::class);
        $this->app->singleton(SettingsRepository::class);
        $this->app->singleton(SettingsAuditService::class);
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(FeatureFlagResolver::class);
        $this->app->singleton(FeatureAvailabilityService::class);
        $this->app->singleton(MaintenanceModeResolver::class);
        $this->app->singleton(FeatureConfigurationService::class);
        $this->app->singleton(\App\Services\Wishlist\WishlistService::class);
        $this->app->singleton(\App\Services\Reviews\ProductReviewService::class);
        $this->app->singleton(PaymentHealthCheck::class);
        $this->app->singleton(ShippingHealthCheck::class);
        $this->app->singleton(NotificationHealthCheck::class);
        $this->app->singleton(StoreHealthCheck::class);
        $this->app->singleton(FeatureHealthCheck::class);
        $this->app->singleton(SecurityHealthCheck::class);
        $this->app->singleton(ConfigurationHealthService::class);
        $this->app->singleton(SettingsAuditQueryService::class);
        $this->app->singleton(ConfigurationDashboardService::class);
    }

    public function boot(): void
    {
        Event::listen(SettingsUpdatedAudit::class, [RecordActivityLog::class, 'record']);
        Event::listen(FeatureConfigurationUpdatedAudit::class, [RecordActivityLog::class, 'record']);
    }
}
