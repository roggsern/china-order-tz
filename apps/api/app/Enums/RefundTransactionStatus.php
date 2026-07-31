<?php

namespace App\Enums;

enum RefundTransactionStatus: string
{
    /** @deprecated Use Requested — kept for backward compatibility with existing rows. */
    case Pending = 'pending';
    case Requested = 'requested';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending, self::Requested => 'Requested',
            self::UnderReview => 'Under review',
            self::Approved => 'Approved',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Rejected => 'Rejected',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Rejected], true);
    }

    public static function tryFromMixed(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if ($normalized === 'pending') {
            return self::Pending;
        }

        return self::tryFrom($normalized);
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending, self::Requested => [self::UnderReview, self::Approved, self::Rejected, self::Failed],
            self::UnderReview => [self::Approved, self::Rejected],
            self::Approved => [self::Processing, self::Failed],
            self::Processing => [self::Completed, self::Failed],
            self::Completed, self::Failed, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
