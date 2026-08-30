<?php

namespace App\Services\Notifications\WhatsApp;

/**
 * Customer-facing money string for WhatsApp template amount slots.
 * Uses the same number_format(2) convention as order/payment notification payloads.
 */
final class WhatsAppAmountFormatter
{
    public function format(mixed $amount, mixed $currency = null): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        if (! is_numeric($amount)) {
            $text = trim((string) $amount);

            return $text === '' ? null : $text;
        }

        $formatted = number_format((float) $amount, 2, '.', '');
        $code = is_string($currency) ? trim($currency) : '';

        return $code !== '' ? $formatted.' '.$code : $formatted;
    }
}
