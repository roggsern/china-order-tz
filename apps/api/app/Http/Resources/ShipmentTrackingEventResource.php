<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ShipmentTrackingEvent */
class ShipmentTrackingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'event_type' => $this->event_type?->value ?? $this->event_type,
            'event_type_label' => $this->event_type?->label(),
            'description' => $this->description,
            'location' => $this->location,
            'event_at' => $this->event_at,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
