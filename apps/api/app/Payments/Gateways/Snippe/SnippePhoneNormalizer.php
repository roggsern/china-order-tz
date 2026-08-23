<?php

namespace App\Payments\Gateways\Snippe;

use InvalidArgumentException;

final class SnippePhoneNormalizer
{
    /**
     * Normalize Tanzanian mobile numbers to Snippe format: 255XXXXXXXXX (12 digits).
     *
     * Accepts: +255712345678, 255712345678, 0712345678, 712345678
     *
     * @throws InvalidArgumentException
     */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException('Phone number is required.');
        }

        if (str_starts_with($digits, '255')) {
            $normalized = $digits;
        } elseif (str_starts_with($digits, '0')) {
            $normalized = '255'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $normalized = '255'.$digits;
        } else {
            throw new InvalidArgumentException('Unsupported phone number format.');
        }

        if (! preg_match('/^255[67]\d{8}$/', $normalized)) {
            throw new InvalidArgumentException('Invalid Tanzania mobile phone number.');
        }

        return $normalized;
    }

    public static function mask(string $normalizedPhone): string
    {
        if (strlen($normalizedPhone) < 8) {
            return '***';
        }

        return substr($normalizedPhone, 0, 5)
            .str_repeat('*', max(0, strlen($normalizedPhone) - 8))
            .substr($normalizedPhone, -3);
    }
}
