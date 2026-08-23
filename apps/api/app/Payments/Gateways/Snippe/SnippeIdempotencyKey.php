<?php

namespace App\Payments\Gateways\Snippe;

final class SnippeIdempotencyKey
{
    private const MAX_LENGTH = 30;

    public static function forPaymentTransaction(string $paymentTransactionId): string
    {
        return substr(hash('sha256', 'snippe:'.$paymentTransactionId), 0, self::MAX_LENGTH);
    }

    public static function maxLength(): int
    {
        return self::MAX_LENGTH;
    }
}
