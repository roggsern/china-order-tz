<?php

namespace App\Enums;

enum TrackingEventType: string
{
    case Booked = 'booked';
    case Collected = 'collected';
    case DepartedOrigin = 'departed_origin';
    case ArrivedDestination = 'arrived_destination';
    case WarehouseReceived = 'warehouse_received';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Booked',
            self::Collected => 'Collected',
            self::DepartedOrigin => 'Departed origin',
            self::ArrivedDestination => 'Arrived destination',
            self::WarehouseReceived => 'Warehouse received',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }
}
