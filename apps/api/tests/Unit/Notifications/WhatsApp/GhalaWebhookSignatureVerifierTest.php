<?php

namespace Tests\Unit\Notifications\WhatsApp;

use App\Services\Notifications\WhatsApp\GhalaWebhookSignatureVerifier;
use Tests\TestCase;

class GhalaWebhookSignatureVerifierTest extends TestCase
{
    private const SECRET = 'whsec_test_ghala_webhook_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['notifications.whatsapp.webhook_secret' => self::SECRET]);
    }

    public function test_valid_signature_is_accepted(): void
    {
        $raw = '{"id":"01MSG","status":"delivered"}';
        $timestamp = (string) time();

        $this->assertTrue(app(GhalaWebhookSignatureVerifier::class)->verify(
            $this->headers($timestamp, $this->sign($timestamp, $raw)),
            $raw,
        ));
    }

    public function test_invalid_and_missing_signature_are_rejected(): void
    {
        $raw = '{"id":"01MSG","status":"delivered"}';
        $timestamp = (string) time();
        $verifier = app(GhalaWebhookSignatureVerifier::class);

        $this->assertFalse($verifier->verify(
            $this->headers($timestamp, 'sha256='.str_repeat('a', 64)),
            $raw,
        ));

        $this->assertFalse($verifier->verify([
            'x-ghala-timestamp' => [$timestamp],
        ], $raw));
    }

    public function test_malformed_and_stale_timestamps_are_rejected(): void
    {
        $raw = '{"id":"01MSG","status":"delivered"}';
        $verifier = app(GhalaWebhookSignatureVerifier::class);

        $this->assertFalse($verifier->verify(
            $this->headers('not-a-timestamp', $this->sign('not-a-timestamp', $raw)),
            $raw,
        ));

        $stale = (string) (time() - 301);
        $this->assertFalse($verifier->verify(
            $this->headers($stale, $this->sign($stale, $raw)),
            $raw,
        ));

        $future = (string) (time() + 301);
        $this->assertFalse($verifier->verify(
            $this->headers($future, $this->sign($future, $raw)),
            $raw,
        ));
    }

    public function test_re_serialized_body_fails_raw_verification(): void
    {
        $raw = '{"id":"01MSG","status":"delivered"}';
        $timestamp = (string) time();
        $signature = $this->sign($timestamp, $raw);
        $reencoded = json_encode(json_decode($raw, true), JSON_PRETTY_PRINT);

        $this->assertFalse(app(GhalaWebhookSignatureVerifier::class)->verify(
            $this->headers($timestamp, $signature),
            (string) $reencoded,
        ));
    }

    /**
     * @return array<string, list<string>>
     */
    private function headers(string $timestamp, string $signature): array
    {
        return [
            'x-ghala-timestamp' => [$timestamp],
            'x-ghala-signature' => [$signature],
        ];
    }

    private function sign(string $timestamp, string $raw): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, self::SECRET);
    }
}
