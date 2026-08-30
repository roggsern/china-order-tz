<?php

namespace App\Services\Notifications\WhatsApp;

use App\Models\Order;
use App\Models\Shipment;

/**
 * Destination/city for order_shipped {{3}} from existing shipment or shipping-address data.
 */
final class ShipmentDestinationResolver
{
    public function forShipment(?Shipment $shipment, ?Order $order = null): ?string
    {
        $destination = trim((string) ($shipment?->destination ?? ''));
        if ($destination !== '') {
            return $destination;
        }

        $order ??= $shipment?->order;
        $order?->loadMissing('shippingAddress');
        $city = trim((string) ($order?->shippingAddress?->city ?? ''));

        return $city !== '' ? $city : null;
    }
}
