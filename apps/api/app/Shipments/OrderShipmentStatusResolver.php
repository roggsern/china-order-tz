<?php

namespace App\Shipments;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;

class OrderShipmentStatusResolver
{
    public function resolve(Order $order): ShipmentStatus
    {
        if ($order->shipment_status !== null) {
            return $order->shipment_status instanceof ShipmentStatus
                ? $order->shipment_status
                : ShipmentStatus::from($order->shipment_status);
        }

        return $this->deriveFromOrderStatus($order->status);
    }

    private function deriveFromOrderStatus(OrderStatus $orderStatus): ShipmentStatus
    {
        return match ($orderStatus) {
            OrderStatus::Pending => ShipmentStatus::OrderReceived,
            OrderStatus::Paid => ShipmentStatus::PaymentConfirmed,
            OrderStatus::Confirmed => ShipmentStatus::SupplierProcessing,
            OrderStatus::Processing => ShipmentStatus::ArrivedChinaWarehouse,
            OrderStatus::Shipped => ShipmentStatus::OutForDelivery,
            OrderStatus::Delivered => ShipmentStatus::Delivered,
            OrderStatus::Cancelled, OrderStatus::Refunded => ShipmentStatus::OrderReceived,
        };
    }

    public function effectiveStatusSqlExpression(): string
    {
        return <<<'SQL'
CASE
    WHEN shipment_status IS NOT NULL THEN shipment_status
    WHEN status = 'pending' THEN 'order_received'
    WHEN status = 'paid' THEN 'payment_confirmed'
    WHEN status = 'confirmed' THEN 'supplier_processing'
    WHEN status = 'processing' THEN 'arrived_china_warehouse'
    WHEN status = 'shipped' THEN 'out_for_delivery'
    WHEN status = 'delivered' THEN 'delivered'
    ELSE 'order_received'
END
SQL;
    }
}
