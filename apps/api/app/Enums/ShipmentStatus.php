<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case OrderReceived = 'order_received';
    case PaymentConfirmed = 'payment_confirmed';
    case SupplierProcessing = 'supplier_processing';
    case PurchasedFromSupplier = 'purchased_from_supplier';
    case ArrivedChinaWarehouse = 'arrived_china_warehouse';
    case QualityInspection = 'quality_inspection';
    case PackedForExport = 'packed_for_export';
    case ShippedFromChina = 'shipped_from_china';
    case CustomsClearance = 'customs_clearance';
    case ArrivedDarWarehouse = 'arrived_dar_warehouse';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::OrderReceived => 'Order Received',
            self::PaymentConfirmed => 'Payment Confirmed',
            self::SupplierProcessing => 'Supplier Processing',
            self::PurchasedFromSupplier => 'Purchased from Supplier',
            self::ArrivedChinaWarehouse => 'Arrived at China Warehouse',
            self::QualityInspection => 'Quality Inspection',
            self::PackedForExport => 'Packed for Export',
            self::ShippedFromChina => 'Shipped from China',
            self::CustomsClearance => 'Customs Clearance',
            self::ArrivedDarWarehouse => 'Arrived at Dar Warehouse',
            self::OutForDelivery => 'Out for Delivery',
            self::Delivered => 'Delivered',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OrderReceived => 'We have received your order and it is being prepared for processing.',
            self::PaymentConfirmed => 'Your payment has been confirmed and your order is moving forward.',
            self::SupplierProcessing => 'We are coordinating with the supplier to prepare your items.',
            self::PurchasedFromSupplier => 'Your items have been purchased from the supplier.',
            self::ArrivedChinaWarehouse => 'Your package has arrived safely at our warehouse in China and is waiting for export.',
            self::QualityInspection => 'Your items are being inspected to ensure they meet our quality standards.',
            self::PackedForExport => 'Your order has been packed and is ready for international shipping.',
            self::ShippedFromChina => 'Your package has left China and is on its way to Tanzania.',
            self::CustomsClearance => 'Your shipment is going through customs clearance.',
            self::ArrivedDarWarehouse => 'Your package has arrived at our Dar es Salaam warehouse.',
            self::OutForDelivery => 'Your order is out for delivery and will reach you soon.',
            self::Delivered => 'Your order has been delivered successfully.',
        };
    }

    /**
     * @return list<self>
     */
    public static function timeline(): array
    {
        return [
            self::OrderReceived,
            self::PaymentConfirmed,
            self::SupplierProcessing,
            self::PurchasedFromSupplier,
            self::ArrivedChinaWarehouse,
            self::QualityInspection,
            self::PackedForExport,
            self::ShippedFromChina,
            self::CustomsClearance,
            self::ArrivedDarWarehouse,
            self::OutForDelivery,
            self::Delivered,
        ];
    }
}
