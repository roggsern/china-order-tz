<?php

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Successful = 'successful';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Processing], true);
    }

    public function allowsReplacementAttempt(): bool
    {
        return in_array($this, [self::Failed, self::Cancelled], true);
    }
}
