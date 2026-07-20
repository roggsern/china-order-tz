<?php

namespace App\Providers;

use App\Events\Audit\GoodsReceivedAudit;
use App\Events\Audit\InventoryReceivedAudit;
use App\Events\Audit\PurchaseOrderConfirmedAudit;
use App\Events\Audit\PurchaseOrderCreatedAudit;
use App\Events\Audit\PurchaseOrderStatusChangedAudit;
use App\Events\Audit\SupplierCreatedAudit;
use App\Events\Audit\SupplierUpdatedAudit;
use App\Events\Procurement\GoodsReceived;
use App\Events\Procurement\InventoryReceived;
use App\Events\Procurement\PurchaseOrderConfirmed;
use App\Events\Procurement\PurchaseOrderCreated;
use App\Events\Procurement\PurchaseOrderStatusChanged;
use App\Events\Procurement\SupplierCreated;
use App\Events\Procurement\SupplierUpdated;
use App\Listeners\Audit\RecordActivityLog;
use App\Listeners\Procurement\HandleProcurementLifecycle;
use App\Services\Procurement\PurchaseOrderEngine;
use App\Services\Procurement\ReceivingEngine;
use App\Services\Procurement\SupplierCostService;
use App\Services\Procurement\SupplierEngine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ProcurementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SupplierEngine::class);
        $this->app->singleton(SupplierCostService::class);
        $this->app->singleton(PurchaseOrderEngine::class);
        $this->app->singleton(ReceivingEngine::class);
    }

    public function boot(): void
    {
        Event::listen(SupplierCreated::class, [HandleProcurementLifecycle::class, 'onSupplierCreated']);
        Event::listen(SupplierUpdated::class, [HandleProcurementLifecycle::class, 'onSupplierUpdated']);
        Event::listen(PurchaseOrderCreated::class, [HandleProcurementLifecycle::class, 'onPurchaseOrderCreated']);
        Event::listen(PurchaseOrderConfirmed::class, [HandleProcurementLifecycle::class, 'onPurchaseOrderConfirmed']);
        Event::listen(PurchaseOrderStatusChanged::class, [HandleProcurementLifecycle::class, 'onPurchaseOrderStatusChanged']);
        Event::listen(GoodsReceived::class, [HandleProcurementLifecycle::class, 'onGoodsReceived']);
        Event::listen(InventoryReceived::class, [HandleProcurementLifecycle::class, 'onInventoryReceived']);

        foreach ([
            SupplierCreatedAudit::class,
            SupplierUpdatedAudit::class,
            PurchaseOrderCreatedAudit::class,
            PurchaseOrderConfirmedAudit::class,
            PurchaseOrderStatusChangedAudit::class,
            GoodsReceivedAudit::class,
            InventoryReceivedAudit::class,
        ] as $auditEvent) {
            Event::listen($auditEvent, [RecordActivityLog::class, 'record']);
        }
    }
}
