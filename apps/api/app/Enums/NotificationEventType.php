<?php

namespace App\Enums;

enum NotificationEventType: string
{
    case OrderCreated = 'order_created';
    case OrderCancelled = 'order_cancelled';
    case PaymentConfirmed = 'payment_confirmed';
    case OrderProcessing = 'order_processing';
    case WarehousePickingStarted = 'warehouse_picking_started';
    case WarehousePickAssigned = 'warehouse_pick_assigned';
    case WarehousePickCompleted = 'warehouse_pick_completed';
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
    case ShipmentStatusUpdated = 'shipment_status_updated';
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
    case RefundRequested = 'refund_requested';
    case RefundApproved = 'refund_approved';
    case RefundCompleted = 'refund_completed';
    case RefundFailed = 'refund_failed';
    case RefundRejected = 'refund_rejected';
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
    case SupportTicketCreated = 'support_ticket_created';
    case SupportTicketAssigned = 'support_ticket_assigned';
    case SupportReplyReceived = 'support_reply_received';
    case SupportTicketResolved = 'support_ticket_resolved';
    case ReviewApproved = 'review_approved';
    case ReviewRejected = 'review_rejected';
    case PurchaseRequirementReady = 'purchase_requirement_ready';
    case ChinaPurchaseCompleted = 'china_purchase_completed';

    public function label(): string
    {
        return match ($this) {
            self::OrderCreated => 'Order Created',
            self::OrderCancelled => 'Order Cancelled',
            self::PaymentConfirmed => 'Payment Confirmed',
            self::OrderProcessing => 'Order Processing',
            self::WarehousePickingStarted => 'Warehouse Picking Started',
            self::WarehousePickAssigned => 'Warehouse Pick Assigned',
            self::WarehousePickCompleted => 'Warehouse Pick Completed',
            self::WarehousePacked => 'Warehouse Packed',
            self::WarehouseReadyToShip => 'Warehouse Ready To Ship',
            self::WarehouseReadyForPickup => 'Warehouse Ready For Pickup',
            self::WarehouseReadyForDeliveryArrangement => 'Warehouse Ready For Delivery Arrangement',
            self::ShipmentCreated => 'Shipment Created',
            self::ShipmentArrivedTanzania => 'Shipment Arrived Tanzania',
            self::CompanyHandoverPickupRequested => 'Company Handover Pickup Requested',
            self::CompanyHandoverDeliveryRequested => 'Company Handover Delivery Requested',
            self::CompanyHandoverCompletedPickup => 'Company Handover Completed Pickup',
            self::CompanyHandoverCompletedDelivery => 'Company Handover Completed Delivery',
            self::TrackingUpdated => 'Tracking Updated',
            self::ShipmentStatusUpdated => 'Shipment Status Updated',
            self::OrderDelivered => 'Order Delivered',
            self::LocalOrderCompletedPickup => 'Local Order Completed Pickup',
            self::LocalOrderCompletedDeliveryArrangement => 'Local Order Completed Delivery Arrangement',
            self::PasswordReset => 'Password Reset',
            self::PasswordChanged => 'Password Changed',
            self::EmailChangeRequested => 'Email Change Requested',
            self::EmailChanged => 'Email Changed',
            self::EmailVerificationRequested => 'Email Verification Requested',
            self::EmailVerified => 'Email Verified',
            self::OtpRequested => 'OTP Requested',
            self::ReturnRequested => 'Return Requested',
            self::ReturnApproved => 'Return Approved',
            self::ReturnRejected => 'Return Rejected',
            self::RefundStarted => 'Refund Started',
            self::RefundRequested => 'Refund Requested',
            self::RefundApproved => 'Refund Approved',
            self::RefundCompleted => 'Refund Completed',
            self::RefundFailed => 'Refund Failed',
            self::RefundRejected => 'Refund Rejected',
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
            self::SupportTicketCreated => 'Support Ticket Created',
            self::SupportTicketAssigned => 'Support Ticket Assigned',
            self::SupportReplyReceived => 'Support Reply Received',
            self::SupportTicketResolved => 'Support Ticket Resolved',
            self::ReviewApproved => 'Review Approved',
            self::ReviewRejected => 'Review Rejected',
            self::PurchaseRequirementReady => 'Purchase Requirement Ready',
            self::ChinaPurchaseCompleted => 'China Purchase Completed',
        };
    }

    public function defaultTemplateKey(NotificationChannel $channel): string
    {
        return $this->value.'.'.$channel->value;
    }
}
