<?php

namespace App\Services\Tracking;

use App\Enums\TrackingEventType;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use Illuminate\Support\Collection;

/**
 * Builds a customer/admin-readable tracking timeline from append-only events.
 */
class TrackingTimelineBuilder
{
    /**
     * @return list<array{
     *     id: string,
     *     event_type: string,
     *     event_type_label: string,
     *     description: string|null,
     *     location: string|null,
     *     event_at: string,
     *     created_by: array{id: string, name: string}|null
     * }>
     */
    public function build(Shipment $shipment): array
    {
        $shipment->loadMissing(['trackingEvents.creator']);

        /** @var Collection<int, ShipmentTrackingEvent> $events */
        $events = $shipment->trackingEvents
            ->sortBy('event_at')
            ->values();

        return $events->map(function (ShipmentTrackingEvent $event) {
            $type = $event->event_type instanceof TrackingEventType
                ? $event->event_type
                : TrackingEventType::from((string) $event->event_type);

            return [
                'id' => $event->id,
                'event_type' => $type->value,
                'event_type_label' => $type->label(),
                'description' => $event->description,
                'location' => $event->location,
                'event_at' => $event->event_at?->toIso8601String(),
                'created_by' => $event->creator
                    ? [
                        'id' => $event->creator->id,
                        'name' => $event->creator->name,
                    ]
                    : null,
            ];
        })->all();
    }
}
