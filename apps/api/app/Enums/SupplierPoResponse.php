<?php

namespace App\Enums;

enum SupplierPoResponse: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case PartiallyAccepted = 'partially_accepted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::PartiallyAccepted => 'Partially accepted',
        };
    }
}
