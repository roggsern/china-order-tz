<?php

namespace App\Enums;

enum NotificationType: string
{
    case OrderCreated = 'order_created';
    case PaymentConfirmed = 'payment_confirmed';
    case ShipmentStatusUpdated = 'shipment_status_updated';
    case WarehousePickingStarted = 'warehouse_picking_started';
    case WarehousePacked = 'warehouse_packed';
    case WarehouseReadyToShip = 'warehouse_ready_to_ship';
    case ShipmentCreated = 'shipment_created';
    case TrackingUpdated = 'tracking_updated';
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
}
