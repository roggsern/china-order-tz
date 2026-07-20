<?php

namespace App\Actions\CustomerOrders;

use App\Enums\DeliveryType;
use App\Enums\TimelineVisibility;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CustomerAgent\CustomerAgentWorkflowEngine;
use App\Services\Tracking\TrackingEngine;
use App\Shipments\OrderShipmentStatusResolver;
use App\Shipments\ShipmentTimelineBuilder;

class ShowShipmentTrackingAction
{
    public function __construct(
        private readonly OrderShipmentStatusResolver $statusResolver,
        private readonly ShipmentTimelineBuilder $timelineBuilder,
        private readonly TrackingEngine $trackingEngine,
        private readonly CustomerAgentWorkflowEngine $customerAgent,
    ) {}

    /**
     * Authoritative customer tracking endpoint payload.
     * Includes legacy source-specific timeline plus unified composed timeline.
     *
     * @return array<string, mixed>
     */
    public function handle(Order $order, User $user): array
    {
        if ($order->user_id !== $user->id) {
            abort(404);
        }

        $order->loadMissing([
            'shipmentStatusHistories',
            'shipments.trackingEvents.creator',
            'fulfillment.shipment.trackingEvents.creator',
            'fulfillment.warehouseJob',
            'deliveryOption',
            'warehouseJob',
        ]);

        $unified = $this->trackingEngine->composeOrderTimeline($order, TimelineVisibility::Customer);

        $deliveryType = $order->deliveryOption?->delivery_type instanceof DeliveryType
            ? $order->deliveryOption->delivery_type
            : DeliveryType::tryFrom((string) ($order->deliveryOption?->delivery_type ?? ''));

        /** @var Shipment|null $shipment */
        $shipment = $order->fulfillment?->shipment
            ?? $order->shipments()->whereNotNull('fulfillment_id')->latest()->first()
            ?? $order->shipments()->latest()->first();

        if ($deliveryType === DeliveryType::CustomerAgent) {
            $payload = $this->customerAgent->trackingPayload($order);

            return [
                'order_number' => $order->order_number,
                'current_status' => $payload['current_status'],
                'current_status_label' => $payload['current_status_label'],
                'shipment' => null,
                'timeline' => $payload['timeline'],
                'unified_timeline' => $unified['timeline'],
                'source' => 'customer_agent_pickup',
                'tracking_ownership' => 'customer_agent',
                'company_transport_tracking' => false,
                'pickup' => $payload['pickup'],
                'authorization_status' => $payload['authorization_status'] ?? null,
                'release_status' => $payload['release_status'] ?? null,
            ];
        }

        if ($shipment !== null) {
            $payload = $this->trackingEngine->buildTrackingPayload($shipment);

            return [
                'order_number' => $order->order_number,
                'current_status' => $payload['current_status'],
                'current_status_label' => $payload['current_status_label'],
                'shipment' => $payload['shipment'],
                'timeline' => $payload['timeline'],
                'unified_timeline' => $unified['timeline'],
                'source' => 'shipment_tracking_events',
                'tracking_ownership' => 'company_shipment',
            ];
        }

        $currentStatus = $this->statusResolver->resolve($order);

        return [
            'order_number' => $order->order_number,
            'current_status' => $currentStatus->value,
            'current_status_label' => $currentStatus->label(),
            'shipment' => null,
            'timeline' => $this->timelineBuilder->build($currentStatus, $order),
            'unified_timeline' => $unified['timeline'],
            'source' => 'order_shipment_status',
            'tracking_ownership' => 'company_shipment',
        ];
    }
}
