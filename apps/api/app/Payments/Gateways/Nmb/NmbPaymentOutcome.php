<?php

namespace App\Payments\Gateways\Nmb;

use App\Enums\PaymentTransactionStatus;

/**
 * Strict MPGS/NMB payment financial outcome.
 *
 * Top-level retrieveOrder result=SUCCESS only means the API call succeeded.
 */
enum NmbPaymentOutcome: string
{
    case Successful = 'successful';
    case Processing = 'processing';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function toTransactionStatus(): PaymentTransactionStatus
    {
        return match ($this) {
            self::Successful => PaymentTransactionStatus::Successful,
            self::Processing => PaymentTransactionStatus::Processing,
            self::Failed => PaymentTransactionStatus::Failed,
            self::Cancelled => PaymentTransactionStatus::Cancelled,
        };
    }

    public function isVerifiedPaid(): bool
    {
        return $this === self::Successful;
    }
}
