<?php

namespace Tests\Unit\Payments\Nmb;

use App\Payments\Gateways\Nmb\PendingNmbCallbackSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingNmbCallbackSignatureVerifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_signature_not_required_by_default(): void
    {
        config(['services.nmb.webhook.require_signature' => false]);

        $verifier = app(PendingNmbCallbackSignatureVerifier::class);

        $this->assertFalse($verifier->isRequired());
        $this->assertTrue($verifier->verify([], '', ['result' => 'SUCCESS']));
    }

    public function test_required_signature_fails_when_not_configured(): void
    {
        config([
            'services.nmb.webhook.require_signature' => true,
            'services.nmb.webhook.secret' => null,
        ]);

        $verifier = app(PendingNmbCallbackSignatureVerifier::class);

        $this->assertTrue($verifier->isRequired());
        $this->assertFalse($verifier->verify([], '{"result":"SUCCESS"}', ['result' => 'SUCCESS']));
    }
}
