<?php

namespace App\Services\Notifications\WhatsApp;

use Illuminate\Support\Facades\Log;

/**
 * Verifies Ghala event-subscription deliveries against the raw request bytes.
 */
final class GhalaWebhookSignatureVerifier
{
    public const HEADER_SIGNATURE = 'x-ghala-signature';

    public const HEADER_TIMESTAMP = 'x-ghala-timestamp';

    public const MAX_SKEW_SECONDS = 300;

    /**
     * @param  array<string, mixed>  $headers
     */
    public function verify(array $headers, string $rawBody): bool
    {
        $secret = (string) config('notifications.whatsapp.webhook_secret', '');
        if ($secret === '') {
            Log::warning('notification.whatsapp.webhook.rejected', [
                'reason' => 'missing_secret',
            ]);

            return false;
        }

        $timestamp = $this->headerValue($headers, self::HEADER_TIMESTAMP);
        $signature = $this->headerValue($headers, self::HEADER_SIGNATURE);

        if ($timestamp === null || $timestamp === '') {
            Log::warning('notification.whatsapp.webhook.rejected', [
                'reason' => 'missing_timestamp',
            ]);

            return false;
        }

        if ($signature === null || $signature === '') {
            Log::warning('notification.whatsapp.webhook.rejected', [
                'reason' => 'missing_signature',
            ]);

            return false;
        }

        if (! ctype_digit($timestamp)) {
            Log::warning('notification.whatsapp.webhook.rejected', [
                'reason' => 'malformed_timestamp',
            ]);

            return false;
        }

        if (abs(time() - (int) $timestamp) > self::MAX_SKEW_SECONDS) {
            Log::warning('notification.whatsapp.webhook.rejected', [
                'reason' => 'timestamp_outside_window',
            ]);

            return false;
        }

        $receivedHex = strtolower(preg_replace('/^sha256=/i', '', trim($signature)) ?? '');
        if ($receivedHex === '' || ! ctype_xdigit($receivedHex) || strlen($receivedHex) !== 64) {
            Log::warning('notification.whatsapp.webhook.rejected', [
                'reason' => 'malformed_signature',
            ]);

            return false;
        }

        $expectedHex = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        if (! hash_equals($expectedHex, $receivedHex)) {
            Log::warning('notification.whatsapp.webhook.rejected', [
                'reason' => 'signature_mismatch',
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $name) {
                continue;
            }

            if (is_array($value)) {
                $first = $value[0] ?? null;

                return is_scalar($first) ? (string) $first : null;
            }

            return is_scalar($value) ? (string) $value : null;
        }

        return null;
    }
}
