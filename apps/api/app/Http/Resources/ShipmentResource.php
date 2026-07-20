<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Shipment */
class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'fulfillment_id' => $this->fulfillment_id,
            'shipment_number' => $this->shipment_number,
            'transport_mode' => $this->transport_mode?->value ?? $this->transport_mode,
            'transport_mode_label' => $this->transport_mode?->label(),
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status?->label(),
            'carrier_name' => $this->carrier_name ?? $this->carrier,
            'tracking_reference' => $this->tracking_reference ?? $this->tracking_number,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'booked_at' => $this->booked_at,
            'shipped_at' => $this->shipped_at,
            'delivered_at' => $this->delivered_at,
            'notes' => $this->notes,
            'fulfillment' => $this->whenLoaded('fulfillment', fn () => [
                'id' => $this->fulfillment->id,
                'strategy' => $this->fulfillment->strategy?->value ?? $this->fulfillment->strategy,
                'status' => $this->fulfillment->status?->value ?? $this->fulfillment->status,
            ]),
            'order' => $this->when(
                $this->relationLoaded('order') || $this->relationLoaded('fulfillment'),
                function () {
                    $order = $this->relationLoaded('order')
                        ? $this->order
                        : $this->fulfillment?->order;

                    if ($order === null) {
                        return null;
                    }

                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status?->value ?? $order->status,
                        'customer' => $order->relationLoaded('user') && $order->user
                            ? [
                                'id' => $order->user->id,
                                'name' => $order->user->name,
                                'email' => $order->user->email,
                            ]
                            : null,
                    ];
                },
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
