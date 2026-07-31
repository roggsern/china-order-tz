<?php

namespace App\Providers;

use App\Events\Audit\StoreBrandingUpdatedAudit;
use App\Events\Audit\StoreCreatedAudit;
use App\Events\Audit\StoreSettingsUpdatedAudit;
use App\Events\Audit\StoreStatusChangedAudit;
use App\Events\Audit\StoreTeamAssignedAudit;
use App\Events\Audit\StoreTeamRemovedAudit;
use App\Events\Audit\StoreTeamUpdatedAudit;
use App\Events\Audit\StoreUpdatedAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Events\Audit\StorePlatformAudit;
use App\Services\Stores\StoreBrandingMediaService;
use App\Models\PosReceipt;
use App\Models\PosSession;
use App\Policies\PosReceiptPolicy;
use App\Policies\PosSessionPolicy;
use App\Services\Pos\PosCatalogService;
use App\Services\Pos\PosReceiptService;
use App\Services\Pos\PosReturnEligibilityService;
use App\Services\Pos\PosReturnService;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\PosSessionCashService;
use App\Services\Pos\PosSessionService;
use App\Services\Pos\Receipt\PosReceiptNumberGenerator;
use App\Services\Pos\Receipt\PosReceiptRenderer;
use App\Services\Pos\Receipt\PosReceiptSnapshotBuilder;
use App\Services\Pos\Receipt\StoreReceiptSettings;
use App\Services\Stores\ActiveStoreContext;
use App\Services\Stores\StoreAssignmentService;
use App\Services\Stores\StoreOperationsDashboardService;
use App\Services\Stores\StoreTeamService;
use App\Services\Stores\StoreService;
use App\Services\Stores\StoreSettingsResolver;
use App\Services\Stores\StoreSettingsService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ActiveStoreContext::class);
        $this->app->singleton(StoreService::class);
        $this->app->singleton(StoreBrandingMediaService::class);
        $this->app->singleton(StoreAssignmentService::class);
        $this->app->singleton(StoreTeamService::class);
        $this->app->singleton(StoreOperationsDashboardService::class);
        $this->app->singleton(StoreSettingsResolver::class);
        $this->app->singleton(StoreSettingsService::class);
        $this->app->singleton(PosCatalogService::class);
        $this->app->singleton(PosSessionCashService::class);
        $this->app->singleton(PosSessionService::class);
        $this->app->singleton(StoreReceiptSettings::class);
        $this->app->singleton(PosReceiptNumberGenerator::class);
        $this->app->singleton(PosReceiptSnapshotBuilder::class);
        $this->app->singleton(PosReceiptRenderer::class);
        $this->app->singleton(PosReceiptService::class);
        $this->app->singleton(PosSaleService::class);
        $this->app->singleton(PosReturnEligibilityService::class);
        $this->app->singleton(PosReturnService::class);
    }

    public function boot(): void
    {
        Gate::policy(PosSession::class, PosSessionPolicy::class);
        Gate::policy(PosReceipt::class, PosReceiptPolicy::class);
        Event::listen(StorePlatformAudit::class, [RecordActivityLog::class, 'record']);
        Event::listen(StoreCreatedAudit::class, [RecordActivityLog::class, 'record']);
        Event::listen(StoreUpdatedAudit::class, [RecordActivityLog::class, 'record']);
        Event::listen(StoreStatusChangedAudit::class, [RecordActivityLog::class, 'record']);
        Event::listen(StoreBrandingUpdatedAudit::class, [RecordActivityLog::class, 'record']);
        Event::listen(StoreSettingsUpdatedAudit::class, [RecordActivityLog::class, 'record']);
        Event::listen(StoreTeamAssignedAudit::class, [RecordActivityLog::class, 'record']);
        Event::listen(StoreTeamRemovedAudit::class, [RecordActivityLog::class, 'record']);
        Event::listen(StoreTeamUpdatedAudit::class, [RecordActivityLog::class, 'record']);
    }
}
