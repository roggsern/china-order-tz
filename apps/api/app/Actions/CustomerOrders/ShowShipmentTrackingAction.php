<?php

namespace App\Actions\CustomerOrders;

use App\Enums\DeliveryType;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CustomerAgent\CustomerAgentWorkflowEngine;
use App\Services\Orders\CustomerOrderProgressResolver;
use App\Services\Orders\CustomerOrderProgressTimelineBuilder;
use App\Services\Tracking\TrackingEngine;

class ShowShipmentTrackingAction
{
    public function __construct(
        private readonly TrackingEngine $trackingEngine,
        private readonly CustomerAgentWorkflowEngine $customerAgent,
        private readonly CustomerOrderProgressResolver $progressResolver,
        private readonly CustomerOrderProgressTimelineBuilder $progressTimeline,
    ) {}

    /**
     * Authoritative customer tracking endpoint payload.
     * Progress projection is the sole customer-facing journey source.
     *
     * @return array<string, mixed>
     */
    public function handle(Order $order, User $user): array
    {
        if ($order->user_id !== $user->id) {
            abort(404);
        }

        $order->loadMissing([
            'payments',
            'shipments.trackingEvents.creator',
            'fulfillment.shipment.trackingEvents.creator',
            'fulfillment.warehouseJob',
            'deliveryOption',
            'warehouseJob',
        ]);

        $progress = $this->progressResolver->resolve($order);
        $timeline = $this->progressTimeline->build($progress);

        $envelope = [
            'order_number' => $order->order_number,
            'current_status' => $progress['current_key'],
            'current_status_label' => $progress['current_label'],
            'timeline' => $timeline,
            'unified_timeline' => $this->progressTimeline->buildUnified($progress),
            'progress' => $progress,
            'source' => 'customer_progress',
        ];

        $deliveryType = $order->deliveryOption?->delivery_type instanceof DeliveryType
            ? $order->deliveryOption->delivery_type
            : DeliveryType::tryFrom((string) ($order->deliveryOption?->delivery_type ?? ''));

        /** @var Shipment|null $shipment */
        $shipment = $order->fulfillment?->shipment
            ?? $order->shipments()->whereNotNull('fulfillment_id')->latest()->first()
            ?? $order->shipments()->latest()->first();

        if ($deliveryType === DeliveryType::CustomerAgent) {
            $payload = $this->customerAgent->trackingPayload($order);

            return array_merge($envelope, [
                'shipment' => null,
                'source' => 'customer_agent_pickup',
                'tracking_ownership' => 'customer_agent',
                'company_transport_tracking' => false,
                'pickup' => $payload['pickup'],
                'authorization_status' => $payload['authorization_status'] ?? null,
                'release_status' => $payload['release_status'] ?? null,
            ]);
        }

        if ($shipment !== null) {
            $payload = $this->trackingEngine->buildTrackingPayload($shipment);

            return array_merge($envelope, [
                'shipment' => $payload['shipment'],
                'tracking_ownership' => 'company_shipment',
            ]);
        }

        return array_merge($envelope, [
            'shipment' => null,
            'tracking_ownership' => 'company_shipment',
        ]);
    }
}
