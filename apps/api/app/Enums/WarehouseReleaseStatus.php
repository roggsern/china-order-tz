<?php

namespace App\Enums;

/**
 * Warehouse release / pickup operational states.
 * Owned by Warehouse (Customer Agent cannot release inventory unilaterally).
 * Distinct from WarehouseJobStatus pick/pack pipeline.
 */
enum WarehouseReleaseStatus: string
{
    case ReadyForPickup = 'ready_for_pickup';
    case PickupScheduled = 'pickup_scheduled';
    case PickedUp = 'picked_up';
    case Released = 'released';
    case Cancelled = 'cancelled';
    case FailedPickup = 'failed_pickup';
    case Reattempt = 'reattempt';

    public function label(): string
    {
        return match ($this) {
            self::ReadyForPickup => 'Ready for pickup',
            self::PickupScheduled => 'Pickup scheduled',
            self::PickedUp => 'Picked up',
            self::Released => 'Released',
            self::Cancelled => 'Cancelled',
            self::FailedPickup => 'Failed pickup',
            self::Reattempt => 'Reattempt',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::ReadyForPickup => [self::PickupScheduled, self::PickedUp, self::Cancelled, self::FailedPickup],
            self::PickupScheduled => [self::PickedUp, self::Cancelled, self::FailedPickup],
            self::PickedUp => [self::Released, self::FailedPickup],
            self::FailedPickup => [self::Reattempt, self::Cancelled],
            self::Reattempt => [self::PickupScheduled, self::PickedUp, self::Cancelled, self::FailedPickup],
            self::Released, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isReleased(): bool
    {
        return $this === self::Released;
    }
}
