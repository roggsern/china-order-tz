<?php

namespace App\Enums;

enum OrderStatus: string
{
    /** @deprecated Prefer PendingPayment — kept for legacy rows */
    case Pending = 'pending';
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    /** Terminal success */
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    /**
     * Money collected; refund workflow outstanding (manual / return refund).
     * Not interchangeable with Refunded.
     */
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PendingPayment => 'Pending payment',
            self::Paid => 'Paid',
            self::Confirmed => 'Confirmed',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::RefundPending => 'Refund pending',
            self::Refunded => 'Refunded',
        };
    }

    public function customerLabel(): string
    {
        return match ($this) {
            self::Pending, self::PendingPayment => 'Awaiting payment',
            self::Paid, self::Confirmed => 'Order confirmed',
            self::Processing => 'Being prepared',
            self::Shipped => 'On the way',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::RefundPending => 'Refund in progress',
            self::Refunded => 'Refunded',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Cancelled,
            self::Refunded,
        ], true);
    }

    public function isPrePayment(): bool
    {
        return in_array($this, [self::Pending, self::PendingPayment], true);
    }

    public function isPayable(): bool
    {
        return $this->isPrePayment();
    }

    /**
     * Authoritative top-level transition matrix.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending, self::PendingPayment => [
                self::Paid,
                self::Cancelled,
            ],
            self::Paid => [
                self::Confirmed,
                self::Processing,
                self::Cancelled,
                self::RefundPending,
            ],
            self::Confirmed => [
                self::Processing,
                self::Cancelled,
                self::RefundPending,
            ],
            self::Processing => [
                self::Shipped,
                self::Delivered, // self-pickup / local handoff without carrier
                self::Cancelled,
                self::RefundPending,
            ],
            self::Shipped => [
                self::Delivered,
                self::RefundPending,
            ],
            self::Delivered => [
                self::Completed,
                self::RefundPending,
            ],
            self::Completed => [
                self::RefundPending,
            ],
            self::RefundPending => [
                self::Refunded,
            ],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true; // idempotent same-state
        }

        return in_array($next, $this->allowedTransitions(), true);
    }
}
