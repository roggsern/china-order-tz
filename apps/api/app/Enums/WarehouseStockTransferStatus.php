<?php

namespace App\Enums;

enum WarehouseStockTransferStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Transferred = 'transferred';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Transferred => 'Transferred',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Transferred, self::Cancelled], true);
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Requested => in_array($next, [self::Approved, self::Cancelled], true),
            self::Approved => in_array($next, [self::Transferred, self::Cancelled], true),
            self::Transferred, self::Cancelled => false,
        };
    }
}
