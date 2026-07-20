<?php

namespace App\Providers;

use App\Events\Audit\AdminLogin;
use App\Events\Audit\AdminLogout;
use App\Events\Audit\NotificationSent;
use App\Events\Audit\NotificationTemplateUpdated;
use App\Events\Audit\OrderCreated;
use App\Events\Audit\PaymentConfirmed;
use App\Events\Audit\ProductCreated;
use App\Events\Audit\ProductUpdated;
use App\Events\Audit\ShipmentCreated;
use App\Events\Audit\ShippingOptionUpdated;
use App\Events\Audit\TrackingEventAdded;
use App\Events\Audit\WarehouseJobCreated;
use App\Events\Audit\WarehouseStatusChanged;
use App\Listeners\Audit\RecordActivityLog;
use App\Services\Audit\ActivityLogger;
use App\Services\Audit\ActivityLogFormatter;
use App\Services\Audit\AuditPlatform;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    /** @var list<class-string> */
    private array $auditableEvents = [
        ProductCreated::class,
        ProductUpdated::class,
        ShippingOptionUpdated::class,
        OrderCreated::class,
        PaymentConfirmed::class,
        WarehouseJobCreated::class,
        WarehouseStatusChanged::class,
        ShipmentCreated::class,
        TrackingEventAdded::class,
        NotificationTemplateUpdated::class,
        NotificationSent::class,
        AdminLogin::class,
        AdminLogout::class,
    ];

    public function register(): void
    {
        $this->app->singleton(ActivityLogFormatter::class);
        $this->app->singleton(ActivityLogger::class);
        $this->app->singleton(AuditPlatform::class);
    }

    public function boot(): void
    {
        foreach ($this->auditableEvents as $eventClass) {
            Event::listen($eventClass, [RecordActivityLog::class, 'record']);
        }
    }
}
