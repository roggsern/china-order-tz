<?php

namespace Tests\Unit\Payments\Snippe;

use App\Payments\Gateways\Snippe\SnippeWebhookSignatureVerifier;
use Tests\TestCase;

class SnippeWebhookSignatureVerifierTest extends TestCase
{
    private SnippeWebhookSignatureVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.snippe.webhook_secret' => 'whsec_test_secret',
            'payments.snippe.webhook_max_age_seconds' => 300,
            'payments.snippe.webhook_max_future_skew_seconds' => 60,
        ]);

        $this->verifier = app(SnippeWebhookSignatureVerifier::class);
    }

    public function test_accepts_valid_signature_on_raw_body(): void
    {
        $rawBody = '{"id":"evt_1","type":"payment.completed"}';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$rawBody}", 'whsec_test_secret');

        $this->assertTrue($this->verifier->verify([
            'x-webhook-timestamp' => [$timestamp],
            'x-webhook-signature' => [$signature],
        ], $rawBody));
    }

    public function test_rejects_tampered_raw_body(): void
    {
        $rawBody = '{"id":"evt_1","type":"payment.completed"}';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$rawBody}", 'whsec_test_secret');

        $this->assertFalse($this->verifier->verify([
            'x-webhook-timestamp' => [$timestamp],
            'x-webhook-signature' => [$signature],
        ], '{"id":"evt_1","type":"payment.failed"}'));
    }

    public function test_rejects_invalid_signature(): void
    {
        $rawBody = '{"id":"evt_1"}';

        $this->assertFalse($this->verifier->verify([
            'x-webhook-timestamp' => [(string) time()],
            'x-webhook-signature' => [str_repeat('a', 64)],
        ], $rawBody));
    }

    public function test_rejects_missing_signature(): void
    {
        $this->assertFalse($this->verifier->verify([
            'x-webhook-timestamp' => [(string) time()],
        ], '{}'));
    }

    public function test_rejects_malformed_signature(): void
    {
        $rawBody = '{}';

        $this->assertFalse($this->verifier->verify([
            'x-webhook-timestamp' => [(string) time()],
            'x-webhook-signature' => ['not-hex'],
        ], $rawBody));
    }

    public function test_rejects_stale_timestamp(): void
    {
        $rawBody = '{"id":"evt_1"}';
        $timestamp = (string) (time() - 400);
        $signature = hash_hmac('sha256', "{$timestamp}.{$rawBody}", 'whsec_test_secret');

        $this->assertFalse($this->verifier->verify([
            'x-webhook-timestamp' => [$timestamp],
            'x-webhook-signature' => [$signature],
        ], $rawBody));
    }

    public function test_rejects_malformed_timestamp(): void
    {
        $rawBody = '{"id":"evt_1"}';

        $this->assertFalse($this->verifier->verify([
            'x-webhook-timestamp' => ['not-a-timestamp'],
            'x-webhook-signature' => [str_repeat('a', 64)],
        ], $rawBody));
    }

    public function test_rejects_unreasonable_future_timestamp(): void
    {
        $rawBody = '{"id":"evt_1"}';
        $timestamp = (string) (time() + 120);
        $signature = hash_hmac('sha256', "{$timestamp}.{$rawBody}", 'whsec_test_secret');

        $this->assertFalse($this->verifier->verify([
            'x-webhook-timestamp' => [$timestamp],
            'x-webhook-signature' => [$signature],
        ], $rawBody));
    }
}
