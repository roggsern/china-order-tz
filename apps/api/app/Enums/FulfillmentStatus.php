<?php

namespace App\Enums;

enum FulfillmentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case ReadyForShipping = 'ready_for_shipping';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::ReadyForShipping => 'Ready for shipping',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled], true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Processing, self::Cancelled],
            self::Processing => [self::ReadyForShipping, self::Cancelled],
            self::ReadyForShipping => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered, self::Cancelled],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
