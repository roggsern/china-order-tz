<?php

namespace App\Services\Orders;

use App\Enums\CustomerOrderProgressKey;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\ShipmentStatus;
use App\Enums\WarehouseJobStatus;
use App\Enums\WarehouseReleaseStatus;
use App\Models\CustomerAgentPickup;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Shipment;

/**
 * Projects internal order state into a single customer communication timeline.
 */
class CustomerOrderProgressResolver
{
    /**
     * @return array{
     *     current_key: string,
     *     current_label: string,
     *     steps: list<array{key: string, label: string, completed: bool}>
     * }
     */
    public function resolve(Order $order): array
    {
        $order->loadMissing([
            'payments',
            'paymentTransactions',
            'fulfillment',
            'fulfillment.warehouseJob',
            'shipments',
            'warehouseJob',
            'deliveryOption',
        ]);

        $terminalKey = $this->resolveTerminalKey($order);
        if ($terminalKey !== null) {
            return $this->buildTerminalProgress($terminalKey);
        }

        $payment = $this->resolveLatestPayment($order);

        if ($this->isAwaitingPayment($order, $payment)) {
            return $this->buildProgress(CustomerOrderProgressKey::AwaitingPayment);
        }

        if ($this->isCustomerAgentDelivery($order)) {
            $currentKey = $this->resolveAgentDeliveryFurthestKey($order, $payment);

            return $this->buildAgentDeliveryProgress($currentKey);
        }

        if ($this->isTanzaniaLocalDelivery($order)) {
            $currentKey = $this->resolveLocalFurthestKey($order, $payment);

            return $this->buildLocalProgress($currentKey);
        }

        if ($this->isCompanyShippingDelivery($order)) {
            $currentKey = $this->resolveCompanyShippingFurthestKey($order, $payment);

            return $this->buildCompanyShippingProgress($currentKey);
        }

        $currentKey = $this->resolveFurthestKey($order, $payment);

        return $this->buildProgress($currentKey);
    }

    private function resolveLatestPayment(Order $order): ?Payment
    {
        if ($order->relationLoaded('payments')) {
            return $order->payments->sortByDesc('created_at')->first();
        }

        return $order->payments()->latest()->first();
    }

    private function isAwaitingPayment(Order $order, ?Payment $payment): bool
    {
        $transaction = $this->resolveLatestRelevantTransaction($order);

        if ($transaction !== null) {
            $transactionStatus = $this->transactionStatus($transaction);

            if ($transactionStatus === PaymentTransactionStatus::Successful) {
                return false;
            }

            if (in_array($transactionStatus, [
                PaymentTransactionStatus::Pending,
                PaymentTransactionStatus::Processing,
            ], true)) {
                return true;
            }
        }

        $paymentStatus = $payment?->status instanceof PaymentStatus
            ? $payment->status
            : PaymentStatus::tryFrom((string) ($payment?->status ?? ''));

        if (in_array($paymentStatus, [PaymentStatus::Pending, PaymentStatus::Initiated], true)) {
            return true;
        }

        $orderStatus = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        return in_array($orderStatus, [OrderStatus::Pending, OrderStatus::PendingPayment], true);
    }

    private function resolveLatestRelevantTransaction(Order $order): ?PaymentTransaction
    {
        $transactions = $order->relationLoaded('paymentTransactions')
            ? $order->paymentTransactions
            : collect();

        if ($transactions->isEmpty()) {
            return null;
        }

        $successful = $transactions
            ->filter(fn (PaymentTransaction $transaction): bool => $this->transactionStatus($transaction) === PaymentTransactionStatus::Successful)
            ->sortByDesc(fn (PaymentTransaction $transaction) => $transaction->completed_at ?? $transaction->created_at)
            ->first();

        if ($successful !== null) {
            return $successful;
        }

        return $transactions
            ->filter(fn (PaymentTransaction $transaction): bool => in_array(
                $this->transactionStatus($transaction),
                [PaymentTransactionStatus::Pending, PaymentTransactionStatus::Processing],
                true,
            ))
            ->sortByDesc('created_at')
            ->first()
            ?? $transactions->sortByDesc('created_at')->first();
    }

    private function transactionStatus(PaymentTransaction $transaction): PaymentTransactionStatus
    {
        return $transaction->status instanceof PaymentTransactionStatus
            ? $transaction->status
            : PaymentTransactionStatus::from((string) $transaction->status);
    }

    private function isCustomerAgentDelivery(Order $order): bool
    {
        $type = $order->deliveryOption?->delivery_type instanceof DeliveryType
            ? $order->deliveryOption->delivery_type
            : DeliveryType::tryFrom((string) ($order->deliveryOption?->delivery_type ?? ''));

        return $type === DeliveryType::CustomerAgent;
    }

    private function isTanzaniaLocalDelivery(Order $order): bool
    {
        $type = $order->deliveryOption?->delivery_type instanceof DeliveryType
            ? $order->deliveryOption->delivery_type
            : DeliveryType::tryFrom((string) ($order->deliveryOption?->delivery_type ?? ''));

        return in_array($type, [DeliveryType::SelfPickup, DeliveryType::NegotiatedDelivery], true);
    }

    private function isCompanyShippingDelivery(Order $order): bool
    {
        $type = $order->deliveryOption?->delivery_type instanceof DeliveryType
            ? $order->deliveryOption->delivery_type
            : DeliveryType::tryFrom((string) ($order->deliveryOption?->delivery_type ?? ''));

        return $type === DeliveryType::CompanyShipping;
    }

    private function resolveCompanyShippingFurthestKey(Order $order, ?Payment $payment): CustomerOrderProgressKey
    {
        $candidates = [
            $this->resolveFromPayment($order, $payment),
            $this->resolveFromOrderStatusForCompanyShipping($order),
            $this->resolveFromFulfillmentForCompanyShipping($order),
            $this->resolveFromWarehouseJobForCompanyShipping($order),
            $this->resolveFromOperationalShipmentForCompanyShipping($order),
            $this->resolveFromLastMileReceivingMethod($order),
            $this->resolveFromShipmentArrival($order),
        ];

        $furthest = CustomerOrderProgressKey::OrderConfirmed;

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate->companyShippingJourneyIndex() > $furthest->companyShippingJourneyIndex()) {
                $furthest = $candidate;
            }
        }

        return $furthest;
    }

    private function resolveFromOrderStatusForCompanyShipping(Order $order): ?CustomerOrderProgressKey
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        return match ($status) {
            OrderStatus::Paid, OrderStatus::Confirmed => CustomerOrderProgressKey::OrderConfirmed,
            OrderStatus::Processing => CustomerOrderProgressKey::Preparing,
            OrderStatus::Shipped => CustomerOrderProgressKey::Shipped,
            OrderStatus::Delivered, OrderStatus::Completed => CustomerOrderProgressKey::Delivered,
            default => null,
        };
    }

    private function resolveFromFulfillmentForCompanyShipping(Order $order): ?CustomerOrderProgressKey
    {
        $fulfillment = $order->fulfillment;
        if ($fulfillment === null) {
            return null;
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) ($fulfillment->status));

        return match ($status) {
            FulfillmentStatus::Pending => CustomerOrderProgressKey::OrderConfirmed,
            FulfillmentStatus::Processing => CustomerOrderProgressKey::Preparing,
            FulfillmentStatus::ReadyForShipping => CustomerOrderProgressKey::Preparing,
            FulfillmentStatus::Shipped => CustomerOrderProgressKey::Shipped,
            FulfillmentStatus::Delivered => CustomerOrderProgressKey::Delivered,
            FulfillmentStatus::Cancelled => null,
            default => null,
        };
    }

    private function resolveFromWarehouseJobForCompanyShipping(Order $order): ?CustomerOrderProgressKey
    {
        $warehouseJob = $order->fulfillment?->warehouseJob ?? $order->warehouseJob;
        if ($warehouseJob === null) {
            return null;
        }

        $status = $warehouseJob->status instanceof WarehouseJobStatus
            ? $warehouseJob->status
            : WarehouseJobStatus::tryFrom((string) ($warehouseJob->status ?? ''));

        return match ($status) {
            WarehouseJobStatus::Pending,
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
            WarehouseJobStatus::Packed => CustomerOrderProgressKey::Preparing,
            WarehouseJobStatus::ReadyToShip => CustomerOrderProgressKey::Preparing,
            WarehouseJobStatus::Cancelled => null,
            default => null,
        };
    }

    private function resolveFromOperationalShipmentForCompanyShipping(Order $order): ?CustomerOrderProgressKey
    {
        /** @var Shipment|null $shipment */
        $shipment = $order->fulfillment?->shipment
            ?? $order->shipments()->whereNotNull('fulfillment_id')->latest()->first()
            ?? $order->shipments()->latest()->first();

        if ($shipment === null) {
            return null;
        }

        $status = $shipment->status instanceof ShipmentLifecycleStatus
            ? $shipment->status
            : ShipmentLifecycleStatus::tryFrom((string) ($shipment->status ?? ''));

        return match ($status) {
            ShipmentLifecycleStatus::Pending,
            ShipmentLifecycleStatus::Booked => CustomerOrderProgressKey::Preparing,
            ShipmentLifecycleStatus::InTransit => CustomerOrderProgressKey::Shipped,
            ShipmentLifecycleStatus::Arrived => CustomerOrderProgressKey::ArrivedTanzania,
            ShipmentLifecycleStatus::Delivered => null,
            default => null,
        };
    }

    private function resolveFromShipmentArrival(Order $order): ?CustomerOrderProgressKey
    {
        $shipment = $order->fulfillment?->shipment
            ?? $order->shipments()->whereNotNull('fulfillment_id')->latest()->first();

        if ($shipment?->arrived_at === null) {
            return null;
        }

        return CustomerOrderProgressKey::ArrivedTanzania;
    }

    private function resolveFromLastMileReceivingMethod(Order $order): ?CustomerOrderProgressKey
    {
        if ($order->deliveryOption?->last_mile_receiving_method === null) {
            return null;
        }

        return CustomerOrderProgressKey::ChooseReceivingMethod;
    }

    private function resolveFurthestKey(Order $order, ?Payment $payment): CustomerOrderProgressKey
    {
        $candidates = [
            $this->resolveFromPayment($order, $payment),
            $this->resolveFromOrderStatus($order),
            $this->resolveFromFulfillment($order),
            $this->resolveFromChinaShipmentStatus($order),
            $this->resolveFromOperationalShipment($order),
            $this->resolveFromWarehouseJob($order),
        ];

        $furthest = CustomerOrderProgressKey::OrderConfirmed;

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate->journeyIndex() > $furthest->journeyIndex()) {
                $furthest = $candidate;
            }
        }

        return $furthest;
    }

    private function resolveAgentDeliveryFurthestKey(Order $order, ?Payment $payment): CustomerOrderProgressKey
    {
        $candidates = [
            $this->resolveFromPayment($order, $payment),
            $this->resolveFromOrderStatusForAgentDelivery($order),
            $this->resolveFromFulfillmentForAgentDelivery($order),
            $this->resolveFromCustomerAgentPickup($order),
        ];

        $furthest = CustomerOrderProgressKey::OrderConfirmed;

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate->agentDeliveryJourneyIndex() > $furthest->agentDeliveryJourneyIndex()) {
                $furthest = $candidate;
            }
        }

        return $furthest;
    }

    private function resolveLocalFurthestKey(Order $order, ?Payment $payment): CustomerOrderProgressKey
    {
        $candidates = [
            $this->resolveFromPayment($order, $payment),
            $this->resolveFromOrderStatusForLocalDelivery($order),
            $this->resolveFromFulfillmentForLocalDelivery($order),
            $this->resolveFromWarehouseJobForLocalDelivery($order),
        ];

        $furthest = CustomerOrderProgressKey::OrderConfirmed;

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate->localJourneyIndex() > $furthest->localJourneyIndex()) {
                $furthest = $candidate;
            }
        }

        return $furthest;
    }

    private function resolveFromOrderStatusForLocalDelivery(Order $order): ?CustomerOrderProgressKey
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        return match ($status) {
            OrderStatus::Paid, OrderStatus::Confirmed => CustomerOrderProgressKey::OrderConfirmed,
            OrderStatus::Processing => CustomerOrderProgressKey::Preparing,
            OrderStatus::Shipped => CustomerOrderProgressKey::ReadyToShip,
            OrderStatus::Delivered, OrderStatus::Completed => CustomerOrderProgressKey::Delivered,
            default => null,
        };
    }

    private function resolveFromFulfillmentForLocalDelivery(Order $order): ?CustomerOrderProgressKey
    {
        $fulfillment = $order->fulfillment;
        if ($fulfillment === null) {
            return null;
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        return match ($status) {
            FulfillmentStatus::Pending => CustomerOrderProgressKey::OrderConfirmed,
            FulfillmentStatus::Processing => CustomerOrderProgressKey::Preparing,
            FulfillmentStatus::ReadyForShipping,
            FulfillmentStatus::Shipped => CustomerOrderProgressKey::ReadyToShip,
            FulfillmentStatus::Delivered => CustomerOrderProgressKey::Delivered,
            FulfillmentStatus::Cancelled => null,
            default => null,
        };
    }

    private function resolveFromWarehouseJobForLocalDelivery(Order $order): ?CustomerOrderProgressKey
    {
        $warehouseJob = $order->fulfillment?->warehouseJob ?? $order->warehouseJob;
        if ($warehouseJob === null) {
            return null;
        }

        $status = $warehouseJob->status instanceof WarehouseJobStatus
            ? $warehouseJob->status
            : WarehouseJobStatus::tryFrom((string) ($warehouseJob->status ?? ''));

        return match ($status) {
            WarehouseJobStatus::Pending,
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
            WarehouseJobStatus::Packed => CustomerOrderProgressKey::Preparing,
            WarehouseJobStatus::ReadyToShip => CustomerOrderProgressKey::ReadyToShip,
            WarehouseJobStatus::Cancelled => null,
            default => null,
        };
    }

    private function resolveFromPayment(Order $order, ?Payment $payment): ?CustomerOrderProgressKey
    {
        $transaction = $this->resolveLatestRelevantTransaction($order);

        if ($transaction !== null && $this->transactionStatus($transaction) === PaymentTransactionStatus::Successful) {
            return CustomerOrderProgressKey::OrderConfirmed;
        }

        if ($payment === null) {
            return null;
        }

        $status = $payment->status instanceof PaymentStatus
            ? $payment->status
            : PaymentStatus::tryFrom((string) $payment->status);

        return $status === PaymentStatus::Paid
            ? CustomerOrderProgressKey::OrderConfirmed
            : null;
    }

    private function resolveFromOrderStatus(Order $order): ?CustomerOrderProgressKey
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        return match ($status) {
            OrderStatus::Paid, OrderStatus::Confirmed => CustomerOrderProgressKey::OrderConfirmed,
            OrderStatus::Processing => CustomerOrderProgressKey::Preparing,
            OrderStatus::Shipped => CustomerOrderProgressKey::Shipped,
            OrderStatus::Delivered, OrderStatus::Completed => CustomerOrderProgressKey::Delivered,
            default => null,
        };
    }

    private function resolveFromOrderStatusForAgentDelivery(Order $order): ?CustomerOrderProgressKey
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        return match ($status) {
            OrderStatus::Paid, OrderStatus::Confirmed => CustomerOrderProgressKey::OrderConfirmed,
            OrderStatus::Processing => CustomerOrderProgressKey::Preparing,
            OrderStatus::Shipped => CustomerOrderProgressKey::SentToAgent,
            OrderStatus::Delivered, OrderStatus::Completed => CustomerOrderProgressKey::DeliveredToAgent,
            default => null,
        };
    }

    private function resolveFromFulfillment(Order $order): ?CustomerOrderProgressKey
    {
        $fulfillment = $order->fulfillment;
        if ($fulfillment === null) {
            return null;
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        return match ($status) {
            FulfillmentStatus::Pending => CustomerOrderProgressKey::OrderConfirmed,
            FulfillmentStatus::Processing => CustomerOrderProgressKey::Preparing,
            FulfillmentStatus::ReadyForShipping => CustomerOrderProgressKey::ReadyToShip,
            FulfillmentStatus::Shipped => CustomerOrderProgressKey::Shipped,
            FulfillmentStatus::Delivered => CustomerOrderProgressKey::Delivered,
            FulfillmentStatus::Cancelled => null,
            default => null,
        };
    }

    private function resolveFromFulfillmentForAgentDelivery(Order $order): ?CustomerOrderProgressKey
    {
        $fulfillment = $order->fulfillment;
        if ($fulfillment === null) {
            return null;
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        return match ($status) {
            FulfillmentStatus::Pending => CustomerOrderProgressKey::OrderConfirmed,
            FulfillmentStatus::Processing,
            FulfillmentStatus::ReadyForShipping => CustomerOrderProgressKey::Preparing,
            FulfillmentStatus::Shipped => CustomerOrderProgressKey::SentToAgent,
            FulfillmentStatus::Delivered => CustomerOrderProgressKey::DeliveredToAgent,
            FulfillmentStatus::Cancelled => null,
            default => null,
        };
    }

    private function resolveFromCustomerAgentPickup(Order $order): ?CustomerOrderProgressKey
    {
        $pickup = CustomerAgentPickup::query()->where('order_id', $order->id)->first();
        if ($pickup === null) {
            return null;
        }

        if ($pickup->handover_completed_at !== null) {
            return CustomerOrderProgressKey::DeliveredToAgent;
        }

        $release = $pickup->release_status instanceof WarehouseReleaseStatus
            ? $pickup->release_status
            : WarehouseReleaseStatus::tryFrom((string) ($pickup->release_status ?? ''));

        if (in_array($release, [WarehouseReleaseStatus::Released, WarehouseReleaseStatus::PickedUp], true)) {
            return CustomerOrderProgressKey::SentToAgent;
        }

        return null;
    }

    private function resolveTerminalKey(Order $order): ?CustomerOrderProgressKey
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        return match ($status) {
            OrderStatus::Cancelled => CustomerOrderProgressKey::Cancelled,
            OrderStatus::RefundPending => CustomerOrderProgressKey::RefundPending,
            OrderStatus::Refunded => CustomerOrderProgressKey::Refunded,
            default => null,
        };
    }

    /**
     * @return array{
     *     current_key: string,
     *     current_label: string,
     *     steps: list<array{key: string, label: string, completed: bool}>
     * }
     */
    private function buildTerminalProgress(CustomerOrderProgressKey $currentKey): array
    {
        return [
            'current_key' => $currentKey->value,
            'current_label' => $currentKey->label(),
            'steps' => [
                [
                    'key' => $currentKey->value,
                    'label' => $currentKey->label(),
                    'completed' => $currentKey === CustomerOrderProgressKey::Refunded,
                ],
            ],
        ];
    }

    private function resolveFromChinaShipmentStatus(Order $order): ?CustomerOrderProgressKey
    {
        $status = $order->shipment_status instanceof ShipmentStatus
            ? $order->shipment_status
            : ShipmentStatus::tryFrom((string) ($order->shipment_status ?? ''));

        if ($status === null) {
            return null;
        }

        return match ($status) {
            ShipmentStatus::OrderReceived,
            ShipmentStatus::PaymentConfirmed => CustomerOrderProgressKey::OrderConfirmed,
            ShipmentStatus::SupplierProcessing,
            ShipmentStatus::PurchasedFromSupplier,
            ShipmentStatus::ArrivedChinaWarehouse,
            ShipmentStatus::QualityInspection,
            ShipmentStatus::PackedForExport => CustomerOrderProgressKey::Preparing,
            ShipmentStatus::ShippedFromChina,
            ShipmentStatus::CustomsClearance,
            ShipmentStatus::ArrivedDarWarehouse,
            ShipmentStatus::OutForDelivery => CustomerOrderProgressKey::Shipped,
            ShipmentStatus::Delivered => CustomerOrderProgressKey::Delivered,
        };
    }

    private function resolveFromOperationalShipment(Order $order): ?CustomerOrderProgressKey
    {
        /** @var Shipment|null $shipment */
        $shipment = $order->fulfillment?->shipment
            ?? $order->shipments()->whereNotNull('fulfillment_id')->latest()->first()
            ?? $order->shipments()->latest()->first();

        if ($shipment === null) {
            return null;
        }

        $status = $shipment->status instanceof ShipmentLifecycleStatus
            ? $shipment->status
            : ShipmentLifecycleStatus::tryFrom((string) ($shipment->status ?? ''));

        return match ($status) {
            ShipmentLifecycleStatus::Pending,
            ShipmentLifecycleStatus::Booked => CustomerOrderProgressKey::ReadyToShip,
            ShipmentLifecycleStatus::InTransit,
            ShipmentLifecycleStatus::Arrived => CustomerOrderProgressKey::Shipped,
            ShipmentLifecycleStatus::Delivered => CustomerOrderProgressKey::Delivered,
            default => null,
        };
    }

    private function resolveFromWarehouseJob(Order $order): ?CustomerOrderProgressKey
    {
        $warehouseJob = $order->fulfillment?->warehouseJob ?? $order->warehouseJob;
        if ($warehouseJob === null) {
            return null;
        }

        $status = $warehouseJob->status instanceof WarehouseJobStatus
            ? $warehouseJob->status
            : WarehouseJobStatus::tryFrom((string) ($warehouseJob->status ?? ''));

        return match ($status) {
            WarehouseJobStatus::Pending,
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
            WarehouseJobStatus::Packed => CustomerOrderProgressKey::Preparing,
            WarehouseJobStatus::ReadyToShip => CustomerOrderProgressKey::ReadyToShip,
            WarehouseJobStatus::Cancelled => null,
            default => null,
        };
    }

    /**
     * @return array{
     *     current_key: string,
     *     current_label: string,
     *     steps: list<array{key: string, label: string, completed: bool}>
     * }
     */
    private function buildProgress(CustomerOrderProgressKey $currentKey): array
    {
        $currentIndex = $currentKey->journeyIndex();

        $steps = [];
        foreach (CustomerOrderProgressKey::journeySteps() as $step) {
            $steps[] = [
                'key' => $step->value,
                'label' => $step->label(),
                'completed' => $currentIndex >= 0 && $step->journeyIndex() <= $currentIndex,
            ];
        }

        return [
            'current_key' => $currentKey->value,
            'current_label' => $currentKey->label(),
            'steps' => $steps,
        ];
    }

    /**
     * @return array{
     *     current_key: string,
     *     current_label: string,
     *     steps: list<array{key: string, label: string, completed: bool}>
     * }
     */
    private function buildAgentDeliveryProgress(CustomerOrderProgressKey $currentKey): array
    {
        $currentIndex = $currentKey->agentDeliveryJourneyIndex();

        $steps = [];
        foreach (CustomerOrderProgressKey::agentDeliveryJourneySteps() as $step) {
            $steps[] = [
                'key' => $step->value,
                'label' => $step->label(),
                'completed' => $currentIndex >= 0 && $step->agentDeliveryJourneyIndex() <= $currentIndex,
            ];
        }

        return [
            'current_key' => $currentKey->value,
            'current_label' => $currentKey->label(),
            'steps' => $steps,
        ];
    }

    /**
     * @return array{
     *     current_key: string,
     *     current_label: string,
     *     steps: list<array{key: string, label: string, completed: bool}>
     * }
     */
    private function buildLocalProgress(CustomerOrderProgressKey $currentKey): array
    {
        $currentIndex = $currentKey->localJourneyIndex();

        $steps = [];
        foreach (CustomerOrderProgressKey::localJourneySteps() as $step) {
            $steps[] = [
                'key' => $step->value,
                'label' => $step->localLabel(),
                'completed' => $currentIndex >= 0 && $step->localJourneyIndex() <= $currentIndex,
            ];
        }

        return [
            'current_key' => $currentKey->value,
            'current_label' => $currentKey->localLabel(),
            'steps' => $steps,
        ];
    }

    /**
     * @return array{
     *     current_key: string,
     *     current_label: string,
     *     steps: list<array{key: string, label: string, completed: bool}>
     * }
     */
    private function buildCompanyShippingProgress(CustomerOrderProgressKey $currentKey): array
    {
        $currentIndex = $currentKey->companyShippingJourneyIndex();

        $steps = [];
        foreach (CustomerOrderProgressKey::companyShippingJourneySteps() as $step) {
            $steps[] = [
                'key' => $step->value,
                'label' => $step->companyShippingLabel(),
                'completed' => $currentIndex >= 0 && $step->companyShippingJourneyIndex() <= $currentIndex,
            ];
        }

        return [
            'current_key' => $currentKey->value,
            'current_label' => $currentKey->companyShippingLabel(),
            'steps' => $steps,
        ];
    }
}
