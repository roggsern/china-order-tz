<?php

namespace App\Enums;

/**
 * Operational shipment lifecycle (Shipment Engine).
 * Distinct from Order timeline ShipmentStatus.
 */
enum ShipmentLifecycleStatus: string
{
    case Pending = 'pending';
    case Booked = 'booked';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Booked => 'Booked',
            self::InTransit => 'In transit',
            self::Arrived => 'Arrived',
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
            self::Pending => [self::Booked, self::Cancelled],
            self::Booked => [self::InTransit, self::Cancelled],
            self::InTransit => [self::Arrived, self::Cancelled],
            self::Arrived => [self::Delivered, self::Cancelled],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
