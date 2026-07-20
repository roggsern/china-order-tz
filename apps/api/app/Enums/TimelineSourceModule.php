<?php

namespace App\Enums;

/**
 * Source module for timeline projections.
 * Business modules own state; TrackingEngine only composes.
 */
enum TimelineSourceModule: string
{
    case OrderLifecycle = 'order_lifecycle';
    case Payment = 'payment';
    case ChinaWorkflow = 'china_workflow';
    case Warehouse = 'warehouse';
    case Shipment = 'shipment';
    case CustomerAgent = 'customer_agent';
    case Tracking = 'tracking';

    public function label(): string
    {
        return match ($this) {
            self::OrderLifecycle => 'Order Lifecycle',
            self::Payment => 'Payment',
            self::ChinaWorkflow => 'China Workflow',
            self::Warehouse => 'Warehouse',
            self::Shipment => 'Shipment',
            self::CustomerAgent => 'Customer Agent',
            self::Tracking => 'Tracking',
        };
    }
}
