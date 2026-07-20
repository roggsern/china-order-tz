<?php

namespace Database\Factories;

use App\Enums\TrackingEventType;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentTrackingEvent>
 */
class ShipmentTrackingEventFactory extends Factory
{
    protected $model = ShipmentTrackingEvent::class;

    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'event_type' => TrackingEventType::Booked,
            'description' => fake()->sentence(),
            'location' => fake()->city(),
            'event_at' => now(),
            'created_by' => null,
        ];
    }
}
