<?php

namespace Tests\Unit\Payments\Snippe;

use App\Payments\Gateways\Snippe\SnippeWebhookUrlValidator;
use Tests\TestCase;

class SnippeWebhookUrlValidatorTest extends TestCase
{
    public function test_accepts_https_path_matching_application_route(): void
    {
        $this->assertTrue(SnippeWebhookUrlValidator::isValid(
            'https://api.example.test/api/v1/payments/snippe/webhook',
            requireHttps: true,
        ));
    }

    public function test_rejects_http_when_https_required(): void
    {
        $this->assertFalse(SnippeWebhookUrlValidator::isValid(
            'http://api.example.test/api/v1/payments/snippe/webhook',
            requireHttps: true,
        ));
    }

    public function test_allows_http_outside_production(): void
    {
        $this->assertTrue(SnippeWebhookUrlValidator::isValid(
            'http://localhost:8000/api/v1/payments/snippe/webhook',
            requireHttps: false,
        ));
    }

    public function test_rejects_wrong_path_and_empty_values(): void
    {
        $this->assertFalse(SnippeWebhookUrlValidator::isValid(''));
        $this->assertFalse(SnippeWebhookUrlValidator::isValid('https://api.example.test/webhooks/snippe'));
        $this->assertFalse(SnippeWebhookUrlValidator::isValid('https://api.example.test/api/v1/payments/nmb/callback'));
        $this->assertFalse(SnippeWebhookUrlValidator::isValid('not-a-url'));
    }
}
