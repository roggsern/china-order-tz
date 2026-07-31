<?php

namespace App\Enums;

enum FulfillmentStatusHistorySource: string
{
    case Admin = 'admin';
    case WarehouseSync = 'warehouse_sync';
    case ShipmentReconciliation = 'shipment_reconciliation';
    case ShipmentDispatch = 'shipment_dispatch';
    case CustomerAgent = 'customer_agent';
    case OrderCancel = 'order_cancel';
    case System = 'system';
}
