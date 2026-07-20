<?php

namespace App\Enums;

enum CustomerTimelineEventType: string
{
    case AccountCreated = 'account_created';
    case OrderCreated = 'order_created';
    case PaymentCompleted = 'payment_completed';
    case OrderCompleted = 'order_completed';
    case ShipmentCreated = 'shipment_created';
    case ShipmentDelivered = 'shipment_delivered';
    case ReturnRequested = 'return_requested';
    case RefundCompleted = 'refund_completed';
    case TagAssigned = 'tag_assigned';
    case TagRemoved = 'tag_removed';
    case NoteAdded = 'note_added';
    case StatusChanged = 'status_changed';
    case CustomerBlocked = 'customer_blocked';
    case CustomerUnblocked = 'customer_unblocked';
    case LoyaltyPointsEarned = 'loyalty_points_earned';
    case LoyaltyPointsRedeemed = 'loyalty_points_redeemed';
    case LoyaltyTierChanged = 'loyalty_tier_changed';

    public function label(): string
    {
        return match ($this) {
            self::AccountCreated => 'Account Created',
            self::OrderCreated => 'Order Created',
            self::PaymentCompleted => 'Payment Completed',
            self::OrderCompleted => 'Order Completed',
            self::ShipmentCreated => 'Shipment Created',
            self::ShipmentDelivered => 'Shipment Delivered',
            self::ReturnRequested => 'Return Requested',
            self::RefundCompleted => 'Refund Completed',
            self::TagAssigned => 'Tag Assigned',
            self::TagRemoved => 'Tag Removed',
            self::NoteAdded => 'Note Added',
            self::StatusChanged => 'Status Changed',
            self::CustomerBlocked => 'Customer Blocked',
            self::CustomerUnblocked => 'Customer Unblocked',
            self::LoyaltyPointsEarned => 'Loyalty Points Earned',
            self::LoyaltyPointsRedeemed => 'Loyalty Points Redeemed',
            self::LoyaltyTierChanged => 'Loyalty Tier Changed',
        };
    }
}
