<?php

namespace App\Providers;

use App\Listeners\Audit\RecordActivityLog;
use App\Events\Audit\StorePlatformAudit;
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
use App\Services\Stores\StoreService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ActiveStoreContext::class);
        $this->app->singleton(StoreService::class);
        $this->app->singleton(StoreAssignmentService::class);
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
    }
}
