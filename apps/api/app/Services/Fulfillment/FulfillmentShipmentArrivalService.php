<?php

namespace App\Services\Fulfillment;

use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\NotificationEventType;
use App\Enums\ShipmentLifecycleStatus;
use App\Models\Fulfillment;
use App\Models\Shipment;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Support\Facades\Log;

/**
 * Opens the China company shipping handover stage when a shipment arrives in Tanzania.
 * Does not complete fulfilment — future handover actions close the order.
 */
class FulfillmentShipmentArrivalService
{
    public function __construct(
        private readonly NotificationPlatform $notifications,
    ) {}

    /**
     * Confirm Tanzania arrival for eligible China company shipping fulfilments.
     */
    public function reconcileArrivedShipment(Shipment $shipment): ?Fulfillment
    {
        $shipment->loadMissing(['fulfillment.order.deliveryOption', 'fulfillment.order.user', 'order.user']);

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

        if ($shipmentStatus !== ShipmentLifecycleStatus::Arrived) {
            return null;
        }

        if ($shipment->arrived_at === null) {
            return null;
        }

        if (! $this->allowsArrivalReconciliation($fulfillment)) {
            return null;
        }

        $fulfillmentStatus = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) ($fulfillment->status ?? ''));

        if ($fulfillmentStatus === null || $fulfillmentStatus->isTerminal()) {
            return null;
        }

        if ($fulfillmentStatus !== FulfillmentStatus::Shipped) {
            return null;
        }

        $this->publishArrivalNotification($shipment, $fulfillment);

        return $fulfillment;
    }

    public function publishesDedicatedArrivalNotification(Shipment $shipment): bool
    {
        $shipment->loadMissing(['fulfillment.order.deliveryOption']);

        if ($shipment->fulfillment_id === null || $shipment->fulfillment === null) {
            return false;
        }

        $shipmentStatus = $shipment->status instanceof ShipmentLifecycleStatus
            ? $shipment->status
            : ShipmentLifecycleStatus::tryFrom((string) ($shipment->status ?? ''));

        if ($shipmentStatus !== ShipmentLifecycleStatus::Arrived || $shipment->arrived_at === null) {
            return false;
        }

        return $this->allowsArrivalReconciliation($shipment->fulfillment);
    }

    private function allowsArrivalReconciliation(Fulfillment $fulfillment): bool
    {
        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) ($fulfillment->strategy ?? ''));

        if ($strategy !== FulfillmentStrategy::China) {
            return false;
        }

        $delivery = $fulfillment->order?->deliveryOption;
        if ($delivery === null) {
            return false;
        }

        $type = $delivery->delivery_type instanceof DeliveryType
            ? $delivery->delivery_type
            : DeliveryType::tryFrom((string) ($delivery->delivery_type ?? ''));

        return $type === DeliveryType::CompanyShipping;
    }

    private function publishArrivalNotification(Shipment $shipment, Fulfillment $fulfillment): void
    {
        $user = $fulfillment->order?->user ?? $shipment->order?->user;
        if ($user === null) {
            return;
        }

        $orderId = $fulfillment->order_id ?? $shipment->order_id;
        $key = 'shipment_arrived_tanzania:'.($orderId ?: $shipment->id).':'.$user->id;

        try {
            $this->notifications->notifyCustomer(
                NotificationEventType::ShipmentArrivedTanzania,
                $user,
                [
                    'customer_name' => $user->name,
                    'order_number' => $fulfillment->order?->order_number ?? $shipment->order?->order_number,
                    'order_id' => $orderId,
                    'shipment_id' => $shipment->id,
                    'location' => $shipment->destination,
                ],
                idempotencyKey: $key,
                correlationKey: $key,
            );
        } catch (\Throwable $e) {
            Log::warning('fulfillment.shipment_arrival_notification_failed', [
                'shipment_id' => $shipment->id,
                'fulfillment_id' => $fulfillment->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
