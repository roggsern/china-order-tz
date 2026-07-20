<?php

namespace Database\Seeders;

use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\WarehouseJobStatus;
use App\Models\Fulfillment;
use App\Services\Shipments\ShipmentEngine;
use App\Services\Warehouse\WarehouseEngine;
use Illuminate\Database\Seeder;

/**
 * Demo shipments only for eligible delivery options.
 * Never creates shipments for customer agent or self pickup.
 */
class ShipmentSeeder extends Seeder
{
    public function run(): void
    {
        /** @var ShipmentEngine $engine */
        $engine = app(ShipmentEngine::class);

        $fulfillments = Fulfillment::query()
            ->where('status', FulfillmentStatus::ReadyForShipping->value)
            ->whereDoesntHave('shipment')
            ->with(['order.deliveryOption'])
            ->limit(10)
            ->get();

        // Promote a few eligible fulfillments to ready_for_shipping for demo coverage.
        if ($fulfillments->isEmpty()) {
            $candidates = Fulfillment::query()
                ->whereDoesntHave('shipment')
                ->with(['order.deliveryOption'])
                ->limit(10)
                ->get()
                ->filter(function (Fulfillment $fulfillment) {
                    $delivery = $fulfillment->order?->deliveryOption;
                    if ($delivery === null) {
                        return false;
                    }
                    $type = $delivery->delivery_type instanceof DeliveryType
                        ? $delivery->delivery_type
                        : DeliveryType::tryFrom((string) $delivery->delivery_type);

                    if ($type === DeliveryType::CompanyShipping) {
                        return true;
                    }

                    if ($type === DeliveryType::NegotiatedDelivery) {
                        $status = $delivery->delivery_status instanceof DeliveryOptionStatus
                            ? $delivery->delivery_status
                            : DeliveryOptionStatus::tryFrom((string) $delivery->delivery_status);

                        return $status === DeliveryOptionStatus::Confirmed;
                    }

                    return false;
                })
                ->take(3);

            foreach ($candidates as $fulfillment) {
                $fulfillment->update([
                    'status' => FulfillmentStatus::ReadyForShipping,
                    'started_at' => $fulfillment->started_at ?? now(),
                ]);
            }

            $fulfillments = Fulfillment::query()
                ->where('status', FulfillmentStatus::ReadyForShipping->value)
                ->whereDoesntHave('shipment')
                ->with(['order.deliveryOption'])
                ->limit(5)
                ->get();
        }

        if ($fulfillments->isEmpty()) {
            $this->command?->warn('ShipmentSeeder skipped: no eligible ready_for_shipping fulfillments.');

            return;
        }

        $created = 0;
        $warehouseEngine = app(WarehouseEngine::class);

        foreach ($fulfillments as $fulfillment) {
            try {
                $job = $fulfillment->warehouseJob
                    ?? $warehouseEngine->createForFulfillment($fulfillment);

                while (
                    $job->status !== WarehouseJobStatus::ReadyToShip
                    && $job->status->nextForward() !== null
                ) {
                    $job = $warehouseEngine->updateStatus($job, [
                        'status' => $job->status->nextForward()->value,
                    ]);
                }

                $engine->createForFulfillment($fulfillment->fresh(['warehouseJob', 'order.deliveryOption']), [
                    'notes' => 'Demo shipment (no carrier booking).',
                ]);
                $created++;
            } catch (\Throwable $e) {
                $this->command?->warn("ShipmentSeeder skipped fulfillment {$fulfillment->id}: {$e->getMessage()}");
            }
        }

        $this->command?->info("ShipmentSeeder created {$created} shipment(s).");
    }
}
