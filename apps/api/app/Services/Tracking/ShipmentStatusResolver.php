<?php

namespace App\Services\Tracking;

use App\Enums\ShipmentLifecycleStatus;
use App\Enums\TrackingEventType;
use App\Models\ShipmentTrackingEvent;

/**
 * Maps the latest tracking event to a cached shipment lifecycle status.
 */
class ShipmentStatusResolver
{
    public function resolveFromEventType(TrackingEventType $eventType): ShipmentLifecycleStatus
    {
        return match ($eventType) {
            TrackingEventType::Booked => ShipmentLifecycleStatus::Pending,
            TrackingEventType::Collected => ShipmentLifecycleStatus::Booked,
            TrackingEventType::DepartedOrigin => ShipmentLifecycleStatus::InTransit,
            TrackingEventType::ArrivedDestination => ShipmentLifecycleStatus::Arrived,
            TrackingEventType::WarehouseReceived => ShipmentLifecycleStatus::Arrived,
            TrackingEventType::OutForDelivery => ShipmentLifecycleStatus::InTransit,
            TrackingEventType::Delivered => ShipmentLifecycleStatus::Delivered,
            TrackingEventType::Cancelled => ShipmentLifecycleStatus::Cancelled,
        };
    }

    public function resolveFromLatestEvent(?ShipmentTrackingEvent $event): ?ShipmentLifecycleStatus
    {
        if ($event === null) {
            return null;
        }

        $type = $event->event_type instanceof TrackingEventType
            ? $event->event_type
            : TrackingEventType::from((string) $event->event_type);

        return $this->resolveFromEventType($type);
    }
}
