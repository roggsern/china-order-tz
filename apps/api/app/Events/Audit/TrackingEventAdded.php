<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\ShipmentTrackingEvent;

class TrackingEventAdded extends BusinessAuditEvent
{
    public static function fromEvent(ShipmentTrackingEvent $event, ?Admin $admin = null): self
    {
        $type = $event->event_type instanceof \BackedEnum
            ? $event->event_type->value
            : (string) $event->event_type;

        return self::make(
            type: ActivityEventType::TrackingEventAdded,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id ?? $event->created_by,
            subjectType: ShipmentTrackingEvent::class,
            subjectId: $event->id,
            description: sprintf('Tracking event "%s" was added.', $type),
            newValues: [
                'event_type' => $type,
                'location' => $event->location,
                'description' => $event->description,
                'event_at' => optional($event->event_at)?->toIso8601String(),
            ],
            metadata: [
                'shipment_id' => $event->shipment_id,
            ],
        );
    }
}
