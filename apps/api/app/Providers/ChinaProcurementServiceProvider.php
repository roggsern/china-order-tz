<?php

namespace App\Providers;

use App\Events\Audit\ChinaPurchaseCompletedAudit;
use App\Events\Audit\ChinaPurchaseMarkedPurchasedAudit;
use App\Events\Audit\ChinaPurchaseRequirementCancelledAudit;
use App\Events\Audit\ChinaPurchaseRequirementCreatedAudit;
use App\Events\Audit\PaymentConfirmed;
use App\Listeners\Audit\RecordActivityLog;
use App\Listeners\China\HandleChinaProcurementLifecycle;
use App\Services\China\Procurement\ChinaCommercialStockService;
use App\Services\China\Procurement\ChinaProcurementBoardEngine;
use App\Services\China\Procurement\ChinaProcurementReconciliationService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ChinaProcurementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChinaCommercialStockService::class);
        $this->app->singleton(ChinaProcurementBoardEngine::class);
        $this->app->singleton(ChinaProcurementReconciliationService::class);
    }

    public function boot(): void
    {
        Event::listen(PaymentConfirmed::class, [HandleChinaProcurementLifecycle::class, 'onPaymentConfirmed']);
        Event::listen(ChinaPurchaseCompletedAudit::class, [HandleChinaProcurementLifecycle::class, 'onPurchaseCompleted']);

        Event::listen(ChinaPurchaseRequirementCreatedAudit::class, [RecordActivityLog::class, 'record']);
        Event::listen(ChinaPurchaseRequirementCancelledAudit::class, [RecordActivityLog::class, 'record']);
        Event::listen(ChinaPurchaseMarkedPurchasedAudit::class, [RecordActivityLog::class, 'record']);
        Event::listen(ChinaPurchaseCompletedAudit::class, [RecordActivityLog::class, 'record']);
    }
}
