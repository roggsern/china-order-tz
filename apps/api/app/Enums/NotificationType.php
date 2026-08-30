<?php

namespace App\Enums;

enum NotificationType: string
{
    case OrderCreated = 'order_created';
    case OrderCancelled = 'order_cancelled';
    case PaymentConfirmed = 'payment_confirmed';
    case OrderProcessing = 'order_processing';
    case ShipmentStatusUpdated = 'shipment_status_updated';
    case WarehousePickingStarted = 'warehouse_picking_started';
    case WarehousePacked = 'warehouse_packed';
    case WarehouseReadyToShip = 'warehouse_ready_to_ship';
    case WarehouseReadyForPickup = 'warehouse_ready_for_pickup';
    case WarehouseReadyForDeliveryArrangement = 'warehouse_ready_for_delivery_arrangement';
    case ShipmentCreated = 'shipment_created';
    case ShipmentArrivedTanzania = 'shipment_arrived_tanzania';
    case CompanyHandoverPickupRequested = 'company_handover_pickup_requested';
    case CompanyHandoverDeliveryRequested = 'company_handover_delivery_requested';
    case CompanyHandoverCompletedPickup = 'company_handover_completed_pickup';
    case CompanyHandoverCompletedDelivery = 'company_handover_completed_delivery';
    case TrackingUpdated = 'tracking_updated';
    case OrderDelivered = 'order_delivered';
    case LocalOrderCompletedPickup = 'local_order_completed_pickup';
    case LocalOrderCompletedDeliveryArrangement = 'local_order_completed_delivery_arrangement';
    case PasswordReset = 'password_reset';
    case PasswordChanged = 'password_changed';
    case EmailChangeRequested = 'email_change_requested';
    case EmailChanged = 'email_changed';
    case EmailVerificationRequested = 'email_verification_requested';
    case EmailVerified = 'email_verified';
    case OtpRequested = 'otp_requested';
    case ReturnRequested = 'return_requested';
    case ReturnApproved = 'return_approved';
    case ReturnRejected = 'return_rejected';
    case RefundStarted = 'refund_started';
    case RefundCompleted = 'refund_completed';
    case PurchaseOrderConfirmed = 'purchase_order_confirmed';
    case PurchaseRequirementReady = 'purchase_requirement_ready';
    case ChinaPurchaseCompleted = 'china_purchase_completed';
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
    case SupportTicketCreated = 'support_ticket_created';
    case SupportTicketAssigned = 'support_ticket_assigned';
    case SupportReplyReceived = 'support_reply_received';
    case SupportTicketResolved = 'support_ticket_resolved';
    case ReviewApproved = 'review_approved';
    case ReviewRejected = 'review_rejected';
}
