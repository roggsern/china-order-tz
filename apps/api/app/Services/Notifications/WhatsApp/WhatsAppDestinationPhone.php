<?php

namespace App\Services\Notifications\WhatsApp;

/**
 * Normalizes users.phone for Ghala: international MSISDN without "+".
 * Tanzanian local forms follow the same digit rules as SnippePhoneNormalizer
 * (destination format only — never a phone source).
 */
final class WhatsAppDestinationPhone
{
    /**
     * @return string|null International digits without "+", or null when unusable.
     */
    public function normalize(?string $phone): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\+[1-9]\d{6,14}$/', $raw) === 1) {
            return ltrim($raw, '+');
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (preg_match('/^\+[1-9]\d{6,14}$/', '+'.$digits) === 1
            && str_starts_with($digits, '255')
        ) {
            return $digits;
        }

        $tanzania = $this->normalizeTanzaniaLocal($digits);
        if ($tanzania !== null) {
            return $tanzania;
        }

        if (preg_match('/^[1-9]\d{6,14}$/', $digits) === 1) {
            return $digits;
        }

        return null;
    }

    private function normalizeTanzaniaLocal(string $digits): ?string
    {
        if (str_starts_with($digits, '255')) {
            $normalized = $digits;
        } elseif (str_starts_with($digits, '0')) {
            $normalized = '255'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $normalized = '255'.$digits;
        } else {
            return null;
        }

        if (preg_match('/^255[67]\d{8}$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }
}
