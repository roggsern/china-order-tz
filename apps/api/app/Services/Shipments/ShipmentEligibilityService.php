<?php

namespace App\Services\Shipments;

use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\TransportMode;
use App\Enums\WarehouseJobStatus;
use App\Models\DeliveryOption;
use App\Models\Fulfillment;
use App\Services\China\ChinaWorkflowEngine;

/**
 * Determines whether China Order TZ is responsible for transporting goods,
 * and whether Customer Agent pickup prerequisites are met.
 *
 * Export Readiness ownership:
 * - ChinaWorkflowEngine owns Export Ready (export_ready_at).
 * - This service ONLY consumes isExportReadyForShipment() for China fulfillments.
 * - It never recalculates QC, consolidation, packing, or document checklist rules.
 *
 * Customer Agent pickup:
 * - Never creates a company shipment.
 * - Consumes Export Ready + warehouse readiness + (optionally) pickup authorization.
 */
class ShipmentEligibilityService
{
    public function __construct(
        private readonly ChinaWorkflowEngine $chinaWorkflow,
    ) {}

    /**
     * @return array{
     *     eligible: bool,
     *     reason: string|null,
     *     transport_mode: TransportMode|null,
     *     delivery_type: string|null
     * }
     */
    public function evaluate(Fulfillment $fulfillment): array
    {
        $fulfillment->loadMissing(['order.deliveryOption', 'shipment', 'warehouseJob']);

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        if ($status !== FulfillmentStatus::ReadyForShipping) {
            return $this->result(
                false,
                'Fulfillment must be ready_for_shipping before a shipment can be created.',
                null,
                $fulfillment->order?->deliveryOption,
            );
        }

        $warehouseJob = $fulfillment->warehouseJob;
        $warehouseStatus = $warehouseJob?->status instanceof WarehouseJobStatus
            ? $warehouseJob->status
            : ($warehouseJob !== null
                ? WarehouseJobStatus::tryFrom((string) $warehouseJob->status)
                : null);

        if ($warehouseStatus !== WarehouseJobStatus::ReadyToShip) {
            return $this->result(
                false,
                'Warehouse job must be ready_to_ship before a shipment can be created.',
                null,
                $fulfillment->order?->deliveryOption,
            );
        }

        // China: consume authoritative Export Ready only — do not re-validate QC/consolidation/docs.
        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) $fulfillment->strategy);

        if ($strategy === FulfillmentStrategy::China
            && ! $this->chinaWorkflow->isExportReadyForShipment($fulfillment)
        ) {
            return $this->result(
                false,
                'China export readiness is required before company shipment.',
                null,
                $fulfillment->order?->deliveryOption,
            );
        }

        /** @var DeliveryOption|null $delivery */
        $delivery = $fulfillment->order?->deliveryOption;

        if ($delivery === null) {
            return $this->result(
                false,
                'Delivery option must be selected before creating a shipment.',
                null,
                null,
            );
        }

        $type = $delivery->delivery_type instanceof DeliveryType
            ? $delivery->delivery_type
            : DeliveryType::tryFrom((string) $delivery->delivery_type);

        if ($type === DeliveryType::CustomerAgent) {
            return $this->result(
                false,
                'Customer Agent',
                null,
                $delivery,
            );
        }

        if ($type === DeliveryType::SelfPickup) {
            return $this->result(
                false,
                'Self Pickup',
                null,
                $delivery,
            );
        }

        if ($type === DeliveryType::CompanyShipping) {
            $method = $delivery->shipping_method instanceof DeliveryShippingMethod
                ? $delivery->shipping_method
                : DeliveryShippingMethod::tryFrom((string) ($delivery->shipping_method ?? ''));

            $mode = match ($method) {
                DeliveryShippingMethod::Air => TransportMode::Air,
                DeliveryShippingMethod::Sea => TransportMode::Sea,
                default => null,
            };

            if ($mode === null) {
                return $this->result(
                    false,
                    'Company shipping requires air or sea shipping method.',
                    null,
                    $delivery,
                );
            }

            return $this->result(true, null, $mode, $delivery);
        }

        if ($type === DeliveryType::NegotiatedDelivery) {
            $deliveryStatus = $delivery->delivery_status instanceof DeliveryOptionStatus
                ? $delivery->delivery_status
                : DeliveryOptionStatus::tryFrom((string) $delivery->delivery_status);

            if ($deliveryStatus !== DeliveryOptionStatus::Confirmed
                && $deliveryStatus !== DeliveryOptionStatus::Completed
            ) {
                return $this->result(
                    false,
                    'Negotiated delivery requires admin confirmation that the company will handle delivery.',
                    TransportMode::Road,
                    $delivery,
                );
            }

            return $this->result(true, null, TransportMode::Road, $delivery);
        }

        return $this->result(
            false,
            'Delivery option is not eligible for company shipment.',
            null,
            $delivery,
        );
    }

    public function isEligible(Fulfillment $fulfillment): bool
    {
        return $this->evaluate($fulfillment)['eligible'];
    }

    /**
     * Customer Agent pickup gate — does NOT enable company shipment.
     * Consumes Export Ready; never recalculates it.
     *
     * @return array{eligible: bool, reason: string|null, delivery_type: string|null}
     */
    public function evaluateCustomerAgentPickup(Fulfillment $fulfillment, bool $requireAuthorization = true): array
    {
        $fulfillment->loadMissing(['order.deliveryOption', 'warehouseJob']);

        $delivery = $fulfillment->order?->deliveryOption;
        $type = $delivery?->delivery_type instanceof DeliveryType
            ? $delivery->delivery_type
            : DeliveryType::tryFrom((string) ($delivery?->delivery_type ?? ''));

        if ($type !== DeliveryType::CustomerAgent) {
            return [
                'eligible' => false,
                'reason' => 'Order is not on Customer Agent delivery.',
                'delivery_type' => $type?->value,
            ];
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        if ($status !== FulfillmentStatus::ReadyForShipping
            && $status !== FulfillmentStatus::Shipped
        ) {
            return [
                'eligible' => false,
                'reason' => 'Fulfillment must be ready_for_shipping before Customer Agent pickup.',
                'delivery_type' => $type->value,
            ];
        }

        $warehouseJob = $fulfillment->warehouseJob;
        $warehouseStatus = $warehouseJob?->status instanceof WarehouseJobStatus
            ? $warehouseJob->status
            : ($warehouseJob !== null
                ? WarehouseJobStatus::tryFrom((string) $warehouseJob->status)
                : null);

        if ($warehouseStatus !== WarehouseJobStatus::ReadyToShip) {
            return [
                'eligible' => false,
                'reason' => 'Warehouse must be ready_to_ship before Customer Agent pickup.',
                'delivery_type' => $type->value,
            ];
        }

        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) $fulfillment->strategy);

        if ($strategy === FulfillmentStrategy::China
            && ! $this->chinaWorkflow->isExportReadyForShipment($fulfillment)
        ) {
            return [
                'eligible' => false,
                'reason' => 'China export readiness is required before Customer Agent pickup.',
                'delivery_type' => $type->value,
            ];
        }

        if ($requireAuthorization) {
            $pickup = \App\Models\CustomerAgentPickup::query()
                ->where('order_id', $fulfillment->order_id)
                ->first();

            if ($pickup === null || ! $pickup->hasValidAuthorization()) {
                return [
                    'eligible' => false,
                    'reason' => 'Valid pickup authorization is required before Customer Agent pickup.',
                    'delivery_type' => $type->value,
                ];
            }
        }

        return [
            'eligible' => true,
            'reason' => null,
            'delivery_type' => $type->value,
        ];
    }

    /**
     * @return array{
     *     eligible: bool,
     *     reason: string|null,
     *     transport_mode: TransportMode|null,
     *     delivery_type: string|null
     * }
     */
    private function result(
        bool $eligible,
        ?string $reason,
        ?TransportMode $mode,
        ?DeliveryOption $delivery,
    ): array {
        $type = $delivery?->delivery_type;

        return [
            'eligible' => $eligible,
            'reason' => $reason,
            'transport_mode' => $mode,
            'delivery_type' => $type instanceof DeliveryType
                ? $type->value
                : ($type !== null ? (string) $type : null),
        ];
    }
}
