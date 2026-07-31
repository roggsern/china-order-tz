<?php

namespace App\Enums;

enum WarehouseJobStatus: string
{
    case Pending = 'pending';
    case Picking = 'picking';
    case Picked = 'picked';
    case Packing = 'packing';
    case Packed = 'packed';
    case ReadyToShip = 'ready_to_ship';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Order ready',
            self::Picking => 'Picking',
            self::Picked => 'Picked',
            self::Packing => 'Packing',
            self::Packed => 'Packed',
            self::ReadyToShip => 'Ready to ship',
            self::Cancelled => 'Cancelled',
            self::Failed => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::ReadyToShip, self::Cancelled, self::Failed], true);
    }

    /**
     * Forward-only transitions (plus cancel before ready_to_ship).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Picking, self::Cancelled],
            self::Picking => [self::Picked, self::Cancelled, self::Failed],
            self::Picked => [self::Packing, self::Cancelled],
            self::Packing => [self::Packed, self::Cancelled, self::Failed],
            self::Packed => [self::ReadyToShip, self::Cancelled],
            self::ReadyToShip, self::Cancelled, self::Failed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function nextForward(): ?self
    {
        return match ($this) {
            self::Pending => self::Picking,
            self::Picking => self::Picked,
            self::Picked => self::Packing,
            self::Packing => self::Packed,
            self::Packed => self::ReadyToShip,
            self::ReadyToShip, self::Cancelled, self::Failed => null,
        };
    }
}
