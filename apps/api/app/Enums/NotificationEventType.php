<?php

namespace App\Enums;

enum NotificationEventType: string
{
    case OrderCreated = 'order_created';
    case PaymentConfirmed = 'payment_confirmed';
    case WarehousePickingStarted = 'warehouse_picking_started';
    case WarehousePacked = 'warehouse_packed';
    case WarehouseReadyToShip = 'warehouse_ready_to_ship';
    case ShipmentCreated = 'shipment_created';
    case TrackingUpdated = 'tracking_updated';
    case ShipmentStatusUpdated = 'shipment_status_updated';
    case OrderDelivered = 'order_delivered';
    case PasswordReset = 'password_reset';
    case OtpRequested = 'otp_requested';
    case ReturnRequested = 'return_requested';
    case ReturnApproved = 'return_approved';
    case ReturnRejected = 'return_rejected';
    case RefundCompleted = 'refund_completed';
    case PurchaseOrderConfirmed = 'purchase_order_confirmed';
    case GoodsReceived = 'goods_received';
    case LowMarginAlert = 'low_margin_alert';
    case CostIncreaseAlert = 'cost_increase_alert';
    case GrowthCampaign = 'growth_campaign';
    case AgentPickupReady = 'agent_pickup_ready';
    case AgentPickupAuthorized = 'agent_pickup_authorized';
    case AgentPickupAuthorizationRevoked = 'agent_pickup_authorization_revoked';
    case AgentPickupScheduled = 'agent_pickup_scheduled';
    case AgentWarehouseReleased = 'agent_warehouse_released';
    case AgentHandoverCompleted = 'agent_handover_completed';

    public function label(): string
    {
        return match ($this) {
            self::OrderCreated => 'Order Created',
            self::PaymentConfirmed => 'Payment Confirmed',
            self::WarehousePickingStarted => 'Warehouse Picking Started',
            self::WarehousePacked => 'Warehouse Packed',
            self::WarehouseReadyToShip => 'Warehouse Ready To Ship',
            self::ShipmentCreated => 'Shipment Created',
            self::TrackingUpdated => 'Tracking Updated',
            self::ShipmentStatusUpdated => 'Shipment Status Updated',
            self::OrderDelivered => 'Order Delivered',
            self::PasswordReset => 'Password Reset',
            self::OtpRequested => 'OTP Requested',
            self::ReturnRequested => 'Return Requested',
            self::ReturnApproved => 'Return Approved',
            self::ReturnRejected => 'Return Rejected',
            self::RefundCompleted => 'Refund Completed',
            self::PurchaseOrderConfirmed => 'Purchase Order Confirmed',
            self::GoodsReceived => 'Goods Received',
            self::LowMarginAlert => 'Low Margin Alert',
            self::CostIncreaseAlert => 'Cost Increase Alert',
            self::GrowthCampaign => 'Growth Campaign',
            self::AgentPickupReady => 'Agent Pickup Ready',
            self::AgentPickupAuthorized => 'Agent Pickup Authorized',
            self::AgentPickupAuthorizationRevoked => 'Agent Pickup Authorization Revoked',
            self::AgentPickupScheduled => 'Agent Pickup Scheduled',
            self::AgentWarehouseReleased => 'Agent Warehouse Released',
            self::AgentHandoverCompleted => 'Agent Handover Completed',
        };
    }

    public function defaultTemplateKey(NotificationChannel $channel): string
    {
        return $this->value.'.'.$channel->value;
    }
}
