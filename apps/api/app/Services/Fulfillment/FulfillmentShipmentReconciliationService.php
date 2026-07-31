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
 * Closes fulfilment lifecycle when operational shipment delivery is recorded.
 * Uses FulfillmentEngine transition rules — never writes status directly.
 */
class FulfillmentShipmentReconciliationService
{
    public function __construct(
        private readonly FulfillmentEngine $fulfillmentEngine,
    ) {}

    /**
     * When a company-operated shipment is delivered, advance shipped fulfilment to delivered.
     */
    public function reconcileDeliveredShipment(Shipment $shipment): ?Fulfillment
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

        if ($shipmentStatus !== ShipmentLifecycleStatus::Delivered) {
            return null;
        }

        $fulfillmentStatus = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        if ($fulfillmentStatus === null) {
            return null;
        }

        if ($fulfillmentStatus === FulfillmentStatus::Delivered) {
            return $fulfillment;
        }

        if ($fulfillmentStatus === FulfillmentStatus::Cancelled) {
            return null;
        }

        if ($fulfillmentStatus !== FulfillmentStatus::Shipped) {
            return null;
        }

        if (! $this->allowsAutomaticCompletion($fulfillment)) {
            return null;
        }

        if (! $fulfillmentStatus->canTransitionTo(FulfillmentStatus::Delivered)) {
            return null;
        }

        try {
            return $this->fulfillmentEngine->updateStatus(
                $fulfillment,
                ['status' => FulfillmentStatus::Delivered->value],
                new FulfillmentStatusUpdateContext(
                    source: FulfillmentStatusHistorySource::ShipmentReconciliation,
                    notes: 'Automatically completed from operational shipment delivery.',
                ),
            );
        } catch (\Throwable $e) {
            Log::warning('fulfillment.shipment_reconciliation_failed', [
                'shipment_id' => $shipment->id,
                'fulfillment_id' => $fulfillment->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function allowsAutomaticCompletion(Fulfillment $fulfillment): bool
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

        if ($type === DeliveryType::CompanyShipping) {
            return false;
        }

        return $type === DeliveryType::NegotiatedDelivery;
    }
}
