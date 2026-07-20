<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Confirmed = 'confirmed';
    case PartiallyReceived = 'partially_received';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Confirmed => 'Confirmed',
            self::PartiallyReceived => 'Partially Received',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Manual PATCH transitions. Receiving completion is applied by ReceivingEngine.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Cancelled],
            self::Sent => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Cancelled],
            self::PartiallyReceived => [self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canReceive(): bool
    {
        return in_array($this, [self::Confirmed, self::PartiallyReceived], true);
    }
}
