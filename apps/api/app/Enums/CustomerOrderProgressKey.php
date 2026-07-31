<?php

namespace App\Enums;

/**
 * Canonical customer-facing order progress milestones.
 * Distinct from internal payment, fulfilment, and shipment statuses.
 */
enum CustomerOrderProgressKey: string
{
    case AwaitingPayment = 'AWAITING_PAYMENT';
    case OrderConfirmed = 'ORDER_CONFIRMED';
    case Preparing = 'PREPARING';
    case ReadyToShip = 'READY_TO_SHIP';
    case Shipped = 'SHIPPED';
    case ArrivedTanzania = 'ARRIVED_TANZANIA';
    case ChooseReceivingMethod = 'CHOOSE_RECEIVING_METHOD';
    case Delivered = 'DELIVERED';
    case SentToAgent = 'SENT_TO_AGENT';
    case DeliveredToAgent = 'DELIVERED_TO_AGENT';
    case Cancelled = 'CANCELLED';
    case RefundPending = 'REFUND_PENDING';
    case Refunded = 'REFUNDED';

    /**
     * Customer-facing label for Buy From TZ (manual logistics) journey.
     */
    public function localLabel(): string
    {
        return match ($this) {
            self::OrderConfirmed => 'Order confirmed',
            self::Preparing => 'Preparing your order',
            self::ReadyToShip => 'Order ready',
            self::Delivered => 'Completed',
            default => $this->label(),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Awaiting payment',
            self::OrderConfirmed => 'Order confirmed',
            self::Preparing => 'Preparing your order',
            self::ReadyToShip => 'Ready to ship',
            self::Shipped => 'Shipped',
            self::ArrivedTanzania => 'Arrived in Tanzania',
            self::ChooseReceivingMethod => 'Choose receiving method',
            self::Delivered => 'Delivered',
            self::SentToAgent => 'Sent to your agent',
            self::DeliveredToAgent => 'Delivered to your agent',
            self::Cancelled => 'Order cancelled',
            self::RefundPending => 'Refund processing',
            self::Refunded => 'Refund completed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::RefundPending, self::Refunded], true);
    }

    /**
     * China company shipping journey — international leg plus Tanzania handover choice.
     *
     * @return list<self>
     */
    public static function companyShippingJourneySteps(): array
    {
        return [
            self::OrderConfirmed,
            self::Preparing,
            self::Shipped,
            self::ArrivedTanzania,
            self::ChooseReceivingMethod,
            self::Delivered,
        ];
    }

    public function companyShippingLabel(): string
    {
        return match ($this) {
            self::Delivered => 'Completed',
            default => $this->label(),
        };
    }

    /**
     * Ordered journey steps exposed to customers (excludes pre-payment).
     *
     * @return list<self>
     */
    public static function journeySteps(): array
    {
        return [
            self::OrderConfirmed,
            self::Preparing,
            self::ReadyToShip,
            self::Shipped,
            self::Delivered,
        ];
    }

    /**
     * Customer agent delivery journey — seller delivers to customer's nominated agent.
     *
     * @return list<self>
     */
    public static function agentDeliveryJourneySteps(): array
    {
        return [
            self::OrderConfirmed,
            self::Preparing,
            self::SentToAgent,
            self::DeliveredToAgent,
        ];
    }

    /**
     * Buy From TZ manual logistics journey — shared by self pickup and delivery arrangement.
     *
     * @return list<self>
     */
    public static function localJourneySteps(): array
    {
        return [
            self::OrderConfirmed,
            self::Preparing,
            self::ReadyToShip,
            self::Delivered,
        ];
    }

    public function companyShippingJourneyIndex(): int
    {
        return match ($this) {
            self::AwaitingPayment => -1,
            self::OrderConfirmed => 0,
            self::Preparing => 1,
            self::Shipped => 2,
            self::ArrivedTanzania => 3,
            self::ChooseReceivingMethod => 4,
            self::Delivered => 5,
            self::Cancelled => 100,
            self::RefundPending => 101,
            self::Refunded => 102,
            default => -1,
        };
    }

    public function journeyIndex(): int
    {
        return match ($this) {
            self::AwaitingPayment => -1,
            self::OrderConfirmed => 0,
            self::Preparing => 1,
            self::ReadyToShip => 2,
            self::Shipped => 3,
            self::Delivered => 4,
            self::SentToAgent => 2,
            self::DeliveredToAgent => 3,
            self::Cancelled => 100,
            self::RefundPending => 101,
            self::Refunded => 102,
        };
    }

    public function agentDeliveryJourneyIndex(): int
    {
        return match ($this) {
            self::AwaitingPayment => -1,
            self::OrderConfirmed => 0,
            self::Preparing => 1,
            self::SentToAgent => 2,
            self::DeliveredToAgent => 3,
            self::Cancelled => 100,
            self::RefundPending => 101,
            self::Refunded => 102,
            default => -1,
        };
    }

    public function localJourneyIndex(): int
    {
        return match ($this) {
            self::AwaitingPayment => -1,
            self::OrderConfirmed => 0,
            self::Preparing => 1,
            self::ReadyToShip => 2,
            self::Delivered => 3,
            self::Cancelled => 100,
            self::RefundPending => 101,
            self::Refunded => 102,
            default => -1,
        };
    }
}
