<?php

namespace App\Enums;

enum ReturnRequestStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Inspection = 'inspection';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Inspection => 'Inspection',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Inspection, self::Cancelled],
            self::Inspection => [self::Completed, self::Cancelled],
            self::Rejected, self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
