<?php

namespace App\Payments\Gateways\Snippe;

use App\Support\Snippe\SnippePaymentLogger;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SnippeWebhookSignatureVerifier
{
    public const HEADER_TIMESTAMP = 'x-webhook-timestamp';

    public const HEADER_SIGNATURE = 'x-webhook-signature';

    public function __construct(
        private readonly SnippePaymentLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $headers
     */
    public function verify(array $headers, string $rawBody): bool
    {
        $secret = SnippeConfig::webhookSecret();
        if ($secret === '') {
            $this->logger->warning('snippe.webhook.signature.rejected', [
                'reason' => 'missing_secret',
            ]);

            return false;
        }

        $timestamp = $this->headerValue($headers, self::HEADER_TIMESTAMP);
        $signature = $this->headerValue($headers, self::HEADER_SIGNATURE);

        if ($timestamp === null || $timestamp === '') {
            $this->logger->warning('snippe.webhook.signature.rejected', [
                'reason' => 'missing_timestamp',
            ]);

            return false;
        }

        if ($signature === null || $signature === '') {
            $this->logger->warning('snippe.webhook.signature.rejected', [
                'reason' => 'missing_signature',
            ]);

            return false;
        }

        if (! $this->isValidTimestamp($timestamp)) {
            $this->logger->warning('snippe.webhook.signature.rejected', [
                'reason' => 'invalid_timestamp',
            ]);

            return false;
        }

        if (! $this->isTimestampWithinWindow($timestamp)) {
            $this->logger->warning('snippe.webhook.signature.rejected', [
                'reason' => 'timestamp_outside_window',
            ]);

            return false;
        }

        $normalizedSignature = $this->normalizeSignature($signature);
        if ($normalizedSignature === null) {
            $this->logger->warning('snippe.webhook.signature.rejected', [
                'reason' => 'malformed_signature',
            ]);

            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);

        if (! hash_equals($expected, $normalizedSignature)) {
            $this->logger->warning('snippe.webhook.signature.rejected', [
                'reason' => 'signature_mismatch',
            ]);

            return false;
        }

        $this->logger->info('snippe.webhook.signature.accepted');

        return true;
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    public function assertVerified(array $headers, string $rawBody): void
    {
        if ($this->verify($headers, $rawBody)) {
            return;
        }

        throw new HttpException(400, 'Invalid Snippe webhook signature.');
    }

    private function isValidTimestamp(string $timestamp): bool
    {
        return ctype_digit($timestamp) && $timestamp !== '';
    }

    private function isTimestampWithinWindow(string $timestamp): bool
    {
        $eventTime = (int) $timestamp;
        $now = time();
        $maxAge = max(1, (int) SnippeConfig::get('webhook_max_age_seconds', 300));
        $maxFutureSkew = max(0, (int) SnippeConfig::get('webhook_max_future_skew_seconds', 60));

        if ($eventTime < ($now - $maxAge)) {
            return false;
        }

        if ($eventTime > ($now + $maxFutureSkew)) {
            return false;
        }

        return true;
    }

    private function normalizeSignature(string $signature): ?string
    {
        $signature = strtolower(trim($signature));

        if ($signature === '' || ! ctype_xdigit($signature) || strlen($signature) !== 64) {
            return null;
        }

        return $signature;
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
