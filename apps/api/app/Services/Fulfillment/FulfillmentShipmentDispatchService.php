<?php

namespace App\Services\Fulfillment;

use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStatusHistorySource;
use App\Enums\ShipmentLifecycleStatus;
use App\Models\Fulfillment;
use App\Models\Shipment;
use Illuminate\Support\Facades\Log;

/**
 * Advances fulfilment to shipped when a company-operated shipment is dispatched.
 * Uses FulfillmentEngine transition rules — never writes status directly.
 */
class FulfillmentShipmentDispatchService
{
    public function __construct(
        private readonly FulfillmentEngine $fulfillmentEngine,
    ) {}

    /**
     * When shipment is in transit (or delivered without a prior in-transit step),
     * advance ready_for_shipping fulfilment to shipped.
     */
    public function reconcileDispatchedShipment(Shipment $shipment): ?Fulfillment
    {
        $shipment->loadMissing(['fulfillment.order.deliveryOption']);

        if ($shipment->fulfillment_id === null) {
            return null;
        }

        $fulfillment = $shipment->fulfillment;
        if ($fulfillment === null) {
            return null;
        }

        $shipmentStatus = $shipment->status instanceof ShipmentLifecycleStatus
            ? $shipment->status
            : ShipmentLifecycleStatus::tryFrom((string) ($shipment->status ?? ''));

        if (! in_array($shipmentStatus, [
            ShipmentLifecycleStatus::InTransit,
            ShipmentLifecycleStatus::Delivered,
        ], true)) {
            return null;
        }

        $fulfillmentStatus = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        if ($fulfillmentStatus === null) {
            return null;
        }

        if (in_array($fulfillmentStatus, [FulfillmentStatus::Shipped, FulfillmentStatus::Delivered], true)) {
            return $fulfillment;
        }

        if ($fulfillmentStatus === FulfillmentStatus::Cancelled) {
            return null;
        }

        if ($fulfillmentStatus !== FulfillmentStatus::ReadyForShipping) {
            return null;
        }

        if (! $this->allowsAutomaticDispatch($fulfillment)) {
            return null;
        }

        if (! $fulfillmentStatus->canTransitionTo(FulfillmentStatus::Shipped)) {
            return null;
        }

        try {
            return $this->fulfillmentEngine->updateStatus(
                $fulfillment,
                ['status' => FulfillmentStatus::Shipped->value],
                new FulfillmentStatusUpdateContext(
                    source: FulfillmentStatusHistorySource::ShipmentDispatch,
                    notes: 'Automatically advanced from operational shipment dispatch.',
                ),
            );
        } catch (\Throwable $e) {
            Log::warning('fulfillment.shipment_dispatch_failed', [
                'shipment_id' => $shipment->id,
                'fulfillment_id' => $fulfillment->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function allowsAutomaticDispatch(Fulfillment $fulfillment): bool
    {
        $delivery = $fulfillment->order?->deliveryOption;
        if ($delivery === null) {
            return false;
        }

        $type = $delivery->delivery_type instanceof DeliveryType
            ? $delivery->delivery_type
            : DeliveryType::tryFrom((string) ($delivery->delivery_type ?? ''));

        if ($type === null) {
            return false;
        }

        if ($type === DeliveryType::CustomerAgent) {
            return false;
        }

        if ($type === DeliveryType::SelfPickup) {
            return false;
        }

        return in_array($type, [
            DeliveryType::CompanyShipping,
            DeliveryType::NegotiatedDelivery,
        ], true);
    }
}
