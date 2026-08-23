<?php

namespace App\Payments\Gateways\Snippe;

use Illuminate\Validation\ValidationException;

final class SnippeAmountValidator
{
    public const MIN_TZS = 500;

    /**
     * @return array{integer_amount: int, currency: string}
     *
     * @throws ValidationException
     */
    public static function assertCollectible(string $amount, string $currency): array
    {
        $currency = strtoupper(trim($currency));

        if ($currency !== 'TZS') {
            throw ValidationException::withMessages([
                'currency' => ['Snippe mobile money payments require TZS currency.'],
            ]);
        }

        if (! self::isWholeTzsAmount($amount)) {
            throw ValidationException::withMessages([
                'amount' => ['Snippe requires whole-shilling TZS amounts without fractional units.'],
            ]);
        }

        $integerAmount = (int) bcdiv($amount, '1', 0);

        if ($integerAmount < self::MIN_TZS) {
            throw ValidationException::withMessages([
                'amount' => ['Snippe minimum payment amount is '.self::MIN_TZS.' TZS.'],
            ]);
        }

        return [
            'integer_amount' => $integerAmount,
            'currency' => $currency,
        ];
    }

    public static function isWholeTzsAmount(string $amount): bool
    {
        if (! is_numeric($amount)) {
            return false;
        }

        $normalized = number_format((float) $amount, 2, '.', '');

        return bccomp($normalized, bcadd(bcdiv($normalized, '1', 0), '0', 0), 2) === 0;
    }

    public static function integerAmountFromTransaction(string $amount): int
    {
        return (int) bcdiv($amount, '1', 0);
    }
}
