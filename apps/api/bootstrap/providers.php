<?php

use App\Payments\Providers\PaymentServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuditServiceProvider;
use App\Providers\CmsServiceProvider;
use App\Providers\ChinaWorkflowServiceProvider;
use App\Providers\CustomerAgentServiceProvider;
use App\Providers\CommerceServiceProvider;
use App\Providers\CostProfitServiceProvider;
use App\Providers\CrmServiceProvider;
use App\Providers\DeliveryServiceProvider;
use App\Providers\FulfillmentServiceProvider;
use App\Providers\GrowthServiceProvider;
use App\Providers\InventoryControlServiceProvider;
use App\Providers\LoyaltyServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\OrderLifecycleServiceProvider;
use App\Providers\OrderSnapshotServiceProvider;
use App\Providers\PosServiceProvider;
use App\Providers\ProcurementServiceProvider;
use App\Providers\ProductShippingServiceProvider;
use App\Providers\PromotionServiceProvider;
use App\Providers\ReportingServiceProvider;
use App\Providers\ReturnsServiceProvider;
use App\Providers\ShipmentServiceProvider;
use App\Providers\TrackingServiceProvider;
use App\Providers\WarehouseServiceProvider;

return [
    AppServiceProvider::class,
    PaymentServiceProvider::class,
    CommerceServiceProvider::class,
    ProcurementServiceProvider::class,
    CostProfitServiceProvider::class,
    CrmServiceProvider::class,
    PromotionServiceProvider::class,
    LoyaltyServiceProvider::class,
    InventoryControlServiceProvider::class,
    GrowthServiceProvider::class,
    CmsServiceProvider::class,
    ChinaWorkflowServiceProvider::class,
    CustomerAgentServiceProvider::class,
    PosServiceProvider::class,
    FulfillmentServiceProvider::class,
    DeliveryServiceProvider::class,
    ProductShippingServiceProvider::class,
    OrderSnapshotServiceProvider::class,
    OrderLifecycleServiceProvider::class,
    WarehouseServiceProvider::class,
    ShipmentServiceProvider::class,
    TrackingServiceProvider::class,
    NotificationServiceProvider::class,
    AuditServiceProvider::class,
    ReturnsServiceProvider::class,
    ReportingServiceProvider::class,
];
