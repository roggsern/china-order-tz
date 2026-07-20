<?php

namespace App\Providers;

use App\Events\Audit\CustomerMetricsRebuiltAudit;
use App\Events\Audit\CustomerNoteAddedAudit;
use App\Events\Audit\CustomerNoteDeletedAudit;
use App\Events\Audit\CustomerNoteUpdatedAudit;
use App\Events\Audit\CustomerProfileCreatedAudit;
use App\Events\Audit\CustomerProfileUpdatedAudit;
use App\Events\Audit\CustomerStatusChangedAudit;
use App\Events\Audit\CustomerTagAssignedAudit;
use App\Events\Audit\CustomerTagRemovedAudit;
use App\Events\Audit\PaymentConfirmed;
use App\Events\Audit\ShipmentCreated as ShipmentCreatedAudit;
use App\Events\Audit\TrackingEventAdded;
use App\Events\Commerce\CommerceOrderCreated;
use App\Events\CostProfit\ProfitCalculated;
use App\Events\Crm\CustomerBlocked;
use App\Events\Crm\CustomerMetricsUpdated;
use App\Events\Crm\CustomerNoteAdded;
use App\Events\Crm\CustomerProfileCreated;
use App\Events\Crm\CustomerProfileUpdated;
use App\Events\Crm\CustomerStatusChanged;
use App\Events\Crm\CustomerTagAssigned;
use App\Events\Crm\CustomerTagRemoved;
use App\Events\Crm\CustomerUnblocked;
use App\Events\Returns\RefundCompleted;
use App\Events\Returns\ReturnRequested;
use App\Listeners\Audit\RecordActivityLog;
use App\Listeners\Crm\HandleCrmLifecycle;
use App\Services\Crm\CustomerCodeGenerator;
use App\Services\Crm\CustomerMetricsService;
use App\Services\Crm\CustomerNoteService;
use App\Services\Crm\CustomerProfileService;
use App\Services\Crm\CustomerSegmentationService;
use App\Services\Crm\CustomerStatusService;
use App\Services\Crm\CustomerTimelineService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CustomerCodeGenerator::class);
        $this->app->singleton(CustomerTimelineService::class);
        $this->app->singleton(CustomerMetricsService::class);
        $this->app->singleton(CustomerProfileService::class);
        $this->app->singleton(CustomerStatusService::class);
        $this->app->singleton(CustomerNoteService::class);
        $this->app->singleton(CustomerSegmentationService::class);
    }

    public function boot(): void
    {
        Event::listen(CustomerProfileCreated::class, [HandleCrmLifecycle::class, 'onProfileCreated']);
        Event::listen(CustomerProfileUpdated::class, [HandleCrmLifecycle::class, 'onProfileUpdated']);
        Event::listen(CustomerStatusChanged::class, [HandleCrmLifecycle::class, 'onStatusChanged']);
        Event::listen(CustomerBlocked::class, [HandleCrmLifecycle::class, 'onBlocked']);
        Event::listen(CustomerUnblocked::class, [HandleCrmLifecycle::class, 'onUnblocked']);
        Event::listen(CustomerTagAssigned::class, [HandleCrmLifecycle::class, 'onTagAssigned']);
        Event::listen(CustomerTagRemoved::class, [HandleCrmLifecycle::class, 'onTagRemoved']);
        Event::listen(CustomerNoteAdded::class, [HandleCrmLifecycle::class, 'onNoteAdded']);
        Event::listen(CustomerMetricsUpdated::class, [HandleCrmLifecycle::class, 'onMetricsUpdated']);

        Event::listen(CommerceOrderCreated::class, [HandleCrmLifecycle::class, 'onCommerceOrderCreated']);
        Event::listen(PaymentConfirmed::class, [HandleCrmLifecycle::class, 'onPaymentConfirmed']);
        Event::listen(ShipmentCreatedAudit::class, [HandleCrmLifecycle::class, 'onShipmentCreated']);
        Event::listen(TrackingEventAdded::class, [HandleCrmLifecycle::class, 'onTrackingEventAdded']);
        Event::listen(ReturnRequested::class, [HandleCrmLifecycle::class, 'onReturnRequested']);
        Event::listen(RefundCompleted::class, [HandleCrmLifecycle::class, 'onRefundCompleted']);
        Event::listen(ProfitCalculated::class, [HandleCrmLifecycle::class, 'onProfitCalculated']);

        foreach ([
            CustomerProfileCreatedAudit::class,
            CustomerProfileUpdatedAudit::class,
            CustomerStatusChangedAudit::class,
            CustomerTagAssignedAudit::class,
            CustomerTagRemovedAudit::class,
            CustomerNoteAddedAudit::class,
            CustomerNoteUpdatedAudit::class,
            CustomerNoteDeletedAudit::class,
            CustomerMetricsRebuiltAudit::class,
        ] as $auditEvent) {
            Event::listen($auditEvent, [RecordActivityLog::class, 'record']);
        }
    }
}
