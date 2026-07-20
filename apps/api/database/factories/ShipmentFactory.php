<?php

namespace Database\Factories;

use App\Enums\ShipmentLifecycleStatus;
use App\Enums\TransportMode;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        $order = Order::factory();

        return [
            'order_id' => $order,
            'fulfillment_id' => null,
            'shipment_number' => 'COTZ-SHIP-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'transport_mode' => TransportMode::Air,
            'status' => ShipmentLifecycleStatus::Pending,
            'carrier_name' => null,
            'tracking_reference' => null,
            'origin' => null,
            'destination' => null,
            'booked_at' => null,
            'shipped_at' => null,
            'delivered_at' => null,
            'notes' => null,
        ];
    }

    public function forFulfillment(Fulfillment $fulfillment): static
    {
        return $this->state(fn () => [
            'order_id' => $fulfillment->order_id,
            'fulfillment_id' => $fulfillment->id,
        ]);
    }
}
