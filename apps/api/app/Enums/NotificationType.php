<?php

namespace App\Enums;

enum NotificationType: string
{
    case OrderCreated = 'order_created';
    case PaymentConfirmed = 'payment_confirmed';
    case ShipmentStatusUpdated = 'shipment_status_updated';
}
