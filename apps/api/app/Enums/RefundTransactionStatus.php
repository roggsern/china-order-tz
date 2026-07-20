<?php

namespace App\Enums;

enum RefundTransactionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Failed],
            self::Approved => [self::Processing, self::Failed],
            self::Processing => [self::Completed, self::Failed],
            self::Completed, self::Failed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
