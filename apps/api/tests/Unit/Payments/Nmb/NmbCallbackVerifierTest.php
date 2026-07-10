<?php

namespace Tests\Unit\Payments\Nmb;

use App\Payments\Gateways\Nmb\NmbCallbackVerifier;
use Tests\TestCase;

class NmbCallbackVerifierTest extends TestCase
{
    private NmbCallbackVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = app(NmbCallbackVerifier::class);
    }

    public function test_valid_payload_requires_identifiable_callback_data(): void
    {
        $this->assertTrue($this->verifier->isValidPayload([
            'result' => 'SUCCESS',
            'session' => ['id' => 'SESSION000123'],
        ]));

        $this->assertFalse($this->verifier->isValidPayload([]));
    }

    public function test_extracts_session_and_order_identifiers(): void
    {
        $payload = [
            'session' => ['id' => 'SESSION000321'],
            'order' => ['id' => 'PAY-2026-000321'],
        ];

        $this->assertSame('SESSION000321', $this->verifier->extractSessionId($payload));
        $this->assertSame('PAY-2026-000321', $this->verifier->extractOrderReference($payload));
    }

    public function test_verification_is_not_implemented_yet(): void
    {
        $this->assertFalse($this->verifier->verify([
            'result' => 'SUCCESS',
            'session' => ['id' => 'SESSION000123'],
        ]));
    }

    public function test_sensitive_values_are_redacted_for_logging(): void
    {
        $sanitized = $this->verifier->sanitizeForLog([
            'result' => 'SUCCESS',
            'password' => 'secret-value',
            'nested' => [
                'token' => 'abc123',
            ],
        ]);

        $this->assertSame('[REDACTED]', $sanitized['password']);
        $this->assertSame('[REDACTED]', $sanitized['nested']['token']);
        $this->assertSame('SUCCESS', $sanitized['result']);
    }
}
