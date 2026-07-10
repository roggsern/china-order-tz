<?php

namespace Tests\Unit\Payments\Nmb;

use App\Models\Payment;
use App\Payments\Gateways\Nmb\NmbApiClient;
use App\Payments\Gateways\Nmb\NmbCheckoutSessionMapper;
use App\Payments\Gateways\Nmb\Requests\NmbInitiateCheckoutRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NmbSandboxIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.nmb.enabled' => true,
            'services.nmb.base_url' => 'https://sandbox.nmb.test',
            'services.nmb.api_version' => '85',
            'services.nmb.merchant_id' => 'TESTMERCHANT',
            'services.nmb.password' => 'sandbox-password',
            'services.nmb.return_url' => 'https://app.chinaorder.test/payments/return',
            'services.nmb.callback_url' => 'https://app.chinaorder.test/webhooks/nmb',
            'services.nmb.merchant_name' => 'China Order TZ',
            'services.nmb.merchant_url' => 'https://chinaorder.test',
        ]);
    }

    public function test_sandbox_request_creation(): void
    {
        $payment = Payment::factory()->nmb()->create([
            'reference' => 'PAY-2026-000123',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        $payload = NmbInitiateCheckoutRequest::fromPayment($payment)->toArray();

        $this->assertSame('INITIATE_CHECKOUT', $payload['apiOperation']);
        $this->assertSame('PURCHASE', $payload['interaction']['operation']);
        $this->assertSame('China Order TZ', $payload['interaction']['merchant']['name']);
        $this->assertSame('https://chinaorder.test', $payload['interaction']['merchant']['url']);
        $this->assertSame('PAY-2026-000123', $payload['order']['id']);
        $this->assertSame('75000.00', $payload['order']['amount']);
        $this->assertSame('TZS', $payload['order']['currency']);
    }

    public function test_basic_authentication(): void
    {
        Http::fake([
            'sandbox.nmb.test/*' => Http::response([
                'result' => 'SUCCESS',
                'session' => [
                    'id' => 'SESSION000123',
                    'successIndicator' => 'abc123xyz',
                ],
            ]),
        ]);

        $client = app(NmbApiClient::class);

        $this->assertSame('merchant.TESTMERCHANT', $client->username());

        $client->initiateCheckout([
            'apiOperation' => 'INITIATE_CHECKOUT',
        ]);

        Http::assertSent(function ($request) {
            $authHeader = $request->header('Authorization')[0] ?? '';
            $expected = 'Basic '.base64_encode('merchant.TESTMERCHANT:sandbox-password');

            return $authHeader === $expected
                && str_contains($request->url(), '/api/rest/version/85/merchant/TESTMERCHANT/session');
        });
    }

    public function test_response_mapping(): void
    {
        $mapper = app(NmbCheckoutSessionMapper::class);

        $session = $mapper->fromResponse([
            'result' => 'SUCCESS',
            'session' => [
                'id' => 'SESSION000999',
                'successIndicator' => 'indicator-999',
            ],
        ]);

        $this->assertTrue($session->success);
        $this->assertSame('SESSION000999', $session->sessionId);
        $this->assertSame('indicator-999', $session->successIndicator);
        $this->assertSame('SESSION000999', $session->gatewayReference);
        $this->assertNull($session->checkoutUrl);
    }

    public function test_retrieve_order_uses_basic_authentication(): void
    {
        Http::fake([
            'sandbox.nmb.test/*' => Http::response([
                'result' => 'SUCCESS',
                'order' => [
                    'id' => 'PAY-2026-000123',
                    'amount' => '75000.00',
                    'currency' => 'TZS',
                ],
            ]),
        ]);

        $client = app(NmbApiClient::class);
        $client->retrieveOrder('PAY-2026-000123');

        Http::assertSent(function ($request) {
            $authHeader = $request->header('Authorization')[0] ?? '';
            $expected = 'Basic '.base64_encode('merchant.TESTMERCHANT:sandbox-password');

            return $authHeader === $expected
                && $request->method() === 'GET'
                && str_contains($request->url(), '/merchant/TESTMERCHANT/order/PAY-2026-000123');
        });
    }

    public function test_checkout_url_mapped_when_returned_by_gateway(): void
    {
        $mapper = app(NmbCheckoutSessionMapper::class);

        $session = $mapper->fromResponse([
            'result' => 'SUCCESS',
            'session' => [
                'id' => 'SESSION000555',
                'successIndicator' => 'indicator-555',
                'redirectUrl' => 'https://sandbox.nmb.test/hosted-checkout/SESSION000555',
            ],
        ]);

        $this->assertSame('https://sandbox.nmb.test/hosted-checkout/SESSION000555', $session->checkoutUrl);
    }
}
