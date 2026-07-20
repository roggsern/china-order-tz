<?php

namespace Database\Seeders;

use App\Enums\TrackingEventType;
use App\Models\Admin;
use App\Models\Shipment;
use App\Services\Tracking\TrackingEngine;
use Illuminate\Database\Seeder;

/**
 * Realistic tracking histories for existing operational shipments.
 * Append-only — does not edit or delete events.
 */
class ShipmentTrackingEventSeeder extends Seeder
{
    public function run(): void
    {
        /** @var TrackingEngine $engine */
        $engine = app(TrackingEngine::class);
        $admin = Admin::query()->first();

        $shipments = Shipment::query()
            ->whereNotNull('fulfillment_id')
            ->whereDoesntHave('trackingEvents')
            ->limit(8)
            ->get();

        if ($shipments->isEmpty()) {
            $this->command?->warn('ShipmentTrackingEventSeeder skipped: no shipments without events.');

            return;
        }

        $sequences = [
            [
                ['event_type' => TrackingEventType::Booked->value, 'location' => 'Origin hub', 'hours' => -72],
                ['event_type' => TrackingEventType::Collected->value, 'location' => 'Origin hub', 'hours' => -60],
                ['event_type' => TrackingEventType::DepartedOrigin->value, 'location' => 'China gateway', 'hours' => -48],
            ],
            [
                ['event_type' => TrackingEventType::Booked->value, 'location' => 'Warehouse', 'hours' => -96],
                ['event_type' => TrackingEventType::Collected->value, 'location' => 'Warehouse', 'hours' => -90],
                ['event_type' => TrackingEventType::DepartedOrigin->value, 'location' => 'Shanghai', 'hours' => -72],
                ['event_type' => TrackingEventType::ArrivedDestination->value, 'location' => 'Dar es Salaam', 'hours' => -24],
                ['event_type' => TrackingEventType::WarehouseReceived->value, 'location' => 'Dar warehouse', 'hours' => -18],
                ['event_type' => TrackingEventType::OutForDelivery->value, 'location' => 'Dar city', 'hours' => -6],
            ],
            [
                ['event_type' => TrackingEventType::Booked->value, 'location' => 'Local depot', 'hours' => -12],
                ['event_type' => TrackingEventType::Collected->value, 'location' => 'Local depot', 'hours' => -10],
                ['event_type' => TrackingEventType::OutForDelivery->value, 'location' => 'City route', 'hours' => -4],
                ['event_type' => TrackingEventType::Delivered->value, 'location' => 'Customer address', 'hours' => -1],
            ],
        ];

        $created = 0;
        foreach ($shipments as $index => $shipment) {
            $sequence = $sequences[$index % count($sequences)];

            try {
                foreach ($sequence as $step) {
                    $engine->recordEvent($shipment, [
                        'event_type' => $step['event_type'],
                        'location' => $step['location'],
                        'description' => 'Demo tracking event: '.$step['event_type'],
                        'event_at' => now()->addHours($step['hours'])->toIso8601String(),
                    ], $admin);
                    $created++;
                }
            } catch (\Throwable $e) {
                $this->command?->warn("ShipmentTrackingEventSeeder skipped shipment {$shipment->id}: {$e->getMessage()}");
            }
        }

        $this->command?->info("ShipmentTrackingEventSeeder created {$created} event(s).");
    }
}
