<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Shipment;

class ShipmentCreated extends BusinessAuditEvent
{
    public static function fromShipment(Shipment $shipment, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::ShipmentCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Shipment::class,
            subjectId: $shipment->id,
            description: sprintf('Shipment %s was created.', $shipment->shipment_number),
            newValues: [
                'shipment_number' => $shipment->shipment_number,
                'order_id' => $shipment->order_id,
                'fulfillment_id' => $shipment->fulfillment_id,
                'transport_mode' => $shipment->transport_mode instanceof \BackedEnum
                    ? $shipment->transport_mode->value
                    : $shipment->transport_mode,
                'status' => $shipment->status instanceof \BackedEnum
                    ? $shipment->status->value
                    : $shipment->status,
            ],
        );
    }
}
