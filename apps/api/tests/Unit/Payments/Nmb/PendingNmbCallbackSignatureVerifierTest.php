<?php

namespace Tests\Unit\Payments\Nmb;

use App\Payments\Gateways\Nmb\NmbWebhookSignatureVerifier;
use App\Payments\Gateways\Nmb\PendingNmbCallbackSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingNmbCallbackSignatureVerifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_signature_not_required_by_default_outside_production(): void
    {
        config([
            'app.env' => 'testing',
            'services.nmb.webhook.require_signature' => false,
        ]);

        $verifier = app(PendingNmbCallbackSignatureVerifier::class);

        $this->assertFalse($verifier->isRequired());
        $this->assertTrue($verifier->verify([], '', ['result' => 'SUCCESS']));
    }

    public function test_required_signature_fails_when_secret_missing(): void
    {
        config([
            'app.env' => 'testing',
            'services.nmb.webhook.require_signature' => true,
            'services.nmb.webhook.secret' => null,
            'services.nmb.webhook.scheme' => NmbWebhookSignatureVerifier::SCHEME_NOTIFICATION_SECRET,
        ]);

        $verifier = app(PendingNmbCallbackSignatureVerifier::class);

        $this->assertTrue($verifier->isRequired());
        $this->assertFalse($verifier->verify([], '{"result":"SUCCESS"}', ['result' => 'SUCCESS']));
    }

    public function test_alias_accepts_valid_notification_secret(): void
    {
        config([
            'app.env' => 'testing',
            'services.nmb.webhook.require_signature' => true,
            'services.nmb.webhook.secret' => 'alias-secret',
            'services.nmb.webhook.scheme' => NmbWebhookSignatureVerifier::SCHEME_NOTIFICATION_SECRET,
        ]);

        $verifier = app(PendingNmbCallbackSignatureVerifier::class);

        $this->assertTrue($verifier->verify(
            ['X-Notification-Secret' => 'alias-secret'],
            '{}',
            ['result' => 'SUCCESS'],
        ));
    }
}
