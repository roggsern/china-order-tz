<?php

namespace App\Payments\Gateways\Nmb;

use App\Payments\Gateways\Nmb\Contracts\NmbCallbackSignatureVerifierInterface;
use App\Support\Nmb\NmbPaymentLogger;

/**
 * Production NMB/MPGS webhook authenticity verifier.
 *
 * Evidenced MPGS contract (Mastercard Gateway Prestashop module):
 * - Header: X-Notification-Secret
 * - Compare shared webhook secret (constant-time)
 * - Observability headers: X-Notification-Id, X-Notification-Attempt
 *
 * Optional HMAC-SHA256 of the raw request body (scheme=hmac_sha256|both)
 * for providers that attach X-Notification-Signature / X-Signature.
 * Default scheme remains notification_secret (provider evidence).
 *
 * Fail-closed: production always requires verification; missing secret rejects.
 */
class NmbWebhookSignatureVerifier implements NmbCallbackSignatureVerifierInterface
{
    public const HEADER_NOTIFICATION_SECRET = 'x-notification-secret';

    public const HEADER_NOTIFICATION_SIGNATURE = 'x-notification-signature';

    public const HEADER_SIGNATURE_FALLBACK = 'x-signature';

    public const HEADER_NOTIFICATION_ID = 'x-notification-id';

    public const HEADER_NOTIFICATION_ATTEMPT = 'x-notification-attempt';

    public const SCHEME_NOTIFICATION_SECRET = 'notification_secret';

    public const SCHEME_HMAC_SHA256 = 'hmac_sha256';

    public const SCHEME_BOTH = 'both';

    public function __construct(
        private readonly NmbPaymentLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $headers, string $rawBody, array $payload): bool
    {
        if (! $this->isRequired()) {
            $this->logger->info('nmb.callback.signature.skipped_non_production', [
                'notification_id' => $this->headerValue($headers, self::HEADER_NOTIFICATION_ID),
                'order_reference' => $this->orderReference($payload),
            ]);

            return true;
        }

        $secret = $this->secret();
        if ($secret === null || $secret === '') {
            $this->logger->warning('nmb.callback.signature.rejected', [
                'reason' => 'missing_secret',
                'notification_id' => $this->headerValue($headers, self::HEADER_NOTIFICATION_ID),
                'order_reference' => $this->orderReference($payload),
            ]);

            return false;
        }

        $scheme = $this->scheme();

        $secretOk = true;
        $hmacOk = true;

        if (in_array($scheme, [self::SCHEME_NOTIFICATION_SECRET, self::SCHEME_BOTH], true)) {
            $secretOk = $this->verifyNotificationSecret($headers, $secret, $payload);
        }

        if (in_array($scheme, [self::SCHEME_HMAC_SHA256, self::SCHEME_BOTH], true)) {
            $hmacOk = $this->verifyHmacSha256($headers, $rawBody, $secret, $payload);
        }

        $accepted = $secretOk && $hmacOk;

        $this->logger->info($accepted ? 'nmb.callback.signature.accepted' : 'nmb.callback.signature.rejected', [
            'scheme' => $scheme,
            'notification_id' => $this->headerValue($headers, self::HEADER_NOTIFICATION_ID),
            'notification_attempt' => $this->headerValue($headers, self::HEADER_NOTIFICATION_ATTEMPT),
            'order_reference' => $this->orderReference($payload),
            'provider_transaction_id' => $this->providerTransactionId($payload),
            'secret_ok' => $secretOk,
            'hmac_ok' => $hmacOk,
        ]);

        return $accepted;
    }

    public function isRequired(): bool
    {
        if ($this->isProduction()) {
            return true;
        }

        return (bool) config('services.nmb.webhook.require_signature', false);
    }

    public function scheme(): string
    {
        $scheme = strtolower(trim((string) config(
            'services.nmb.webhook.scheme',
            self::SCHEME_NOTIFICATION_SECRET,
        )));

        return in_array($scheme, [
            self::SCHEME_NOTIFICATION_SECRET,
            self::SCHEME_HMAC_SHA256,
            self::SCHEME_BOTH,
        ], true) ? $scheme : self::SCHEME_NOTIFICATION_SECRET;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $payload
     */
    private function verifyNotificationSecret(array $headers, string $secret, array $payload): bool
    {
        $provided = $this->headerValue($headers, self::HEADER_NOTIFICATION_SECRET);

        if ($provided === null || $provided === '') {
            $this->logger->warning('nmb.callback.signature.rejected', [
                'reason' => 'missing_notification_secret_header',
                'order_reference' => $this->orderReference($payload),
            ]);

            return false;
        }

        if (! hash_equals($secret, $provided)) {
            $this->logger->warning('nmb.callback.signature.rejected', [
                'reason' => 'notification_secret_mismatch',
                'order_reference' => $this->orderReference($payload),
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $payload
     */
    private function verifyHmacSha256(array $headers, string $rawBody, string $secret, array $payload): bool
    {
        $provided = $this->headerValue($headers, self::HEADER_NOTIFICATION_SIGNATURE)
            ?? $this->headerValue($headers, self::HEADER_SIGNATURE_FALLBACK);

        if ($provided === null || $provided === '') {
            $this->logger->warning('nmb.callback.signature.rejected', [
                'reason' => 'missing_hmac_header',
                'order_reference' => $this->orderReference($payload),
            ]);

            return false;
        }

        $provided = $this->normalizeHmacHeader($provided);
        if ($provided === null) {
            $this->logger->warning('nmb.callback.signature.rejected', [
                'reason' => 'malformed_hmac_header',
                'order_reference' => $this->orderReference($payload),
            ]);

            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (! hash_equals($expected, $provided)) {
            $this->logger->warning('nmb.callback.signature.rejected', [
                'reason' => 'hmac_mismatch',
                'order_reference' => $this->orderReference($payload),
            ]);

            return false;
        }

        return true;
    }

    private function normalizeHmacHeader(string $value): ?string
    {
        $value = trim($value);

        if (str_starts_with(strtolower($value), 'sha256=')) {
            $value = substr($value, 7);
        }

        $value = strtolower($value);

        if ($value === '' || ! ctype_xdigit($value) || strlen($value) !== 64) {
            return null;
        }

        return $value;
    }

    private function secret(): ?string
    {
        $secret = config('services.nmb.webhook.secret');

        return is_string($secret) ? $secret : null;
    }

    private function isProduction(): bool
    {
        return app()->environment('production')
            || (string) config('app.env') === 'production';
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function orderReference(array $payload): ?string
    {
        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];

        if (isset($order['id']) && filled($order['id'])) {
            return (string) $order['id'];
        }

        if (isset($order['reference']) && filled($order['reference'])) {
            return (string) $order['reference'];
        }

        return isset($payload['order_id']) ? (string) $payload['order_id'] : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function providerTransactionId(array $payload): ?string
    {
        $transaction = is_array($payload['transaction'] ?? null) ? $payload['transaction'] : [];

        if (isset($transaction['id']) && filled($transaction['id'])) {
            return (string) $transaction['id'];
        }

        return isset($payload['transactionId']) ? (string) $payload['transactionId'] : null;
    }
}
