<?php

namespace Tests\Unit\Payments\Nmb;

use App\Models\Payment;
use App\Payments\Gateways\Nmb\NmbVerificationMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NmbVerificationMapperTest extends TestCase
{
    use RefreshDatabase;

    private NmbVerificationMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = app(NmbVerificationMapper::class);
    }

    public function test_maps_successful_verification(): void
    {
        $payment = Payment::factory()->nmb()->create([
            'reference' => 'PAY-2026-000123',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        $result = $this->mapper->fromResponse([
            'result' => 'SUCCESS',
            'response' => ['gatewayCode' => 'APPROVED'],
            'order' => [
                'id' => 'PAY-2026-000123',
                'amount' => '75000.00',
                'currency' => 'TZS',
                'status' => 'CAPTURED',
                'authenticationStatus' => 'AUTHENTICATION_SUCCESSFUL',
                'totalAuthorizedAmount' => '75000.00',
                'totalCapturedAmount' => '75000.00',
            ],
            'transaction' => [
                'id' => 'TRANS000123',
                'result' => 'SUCCESS',
                'type' => 'PAYMENT',
            ],
        ], $payment);

        $this->assertTrue($result->verified);
        $this->assertSame('PAY-2026-000123', $result->orderId);
        $this->assertSame('TRANS000123', $result->transactionId);
    }

    public function test_rejects_mismatched_order_reference(): void
    {
        $payment = Payment::factory()->nmb()->create([
            'reference' => 'PAY-2026-000123',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        $result = $this->mapper->fromResponse([
            'result' => 'SUCCESS',
            'response' => ['gatewayCode' => 'APPROVED'],
            'order' => [
                'id' => 'PAY-2026-000999',
                'amount' => '75000.00',
                'currency' => 'TZS',
                'status' => 'CAPTURED',
                'totalAuthorizedAmount' => '75000.00',
                'totalCapturedAmount' => '75000.00',
            ],
        ], $payment);

        $this->assertFalse($result->verified);
    }

    public function test_pending_authentication_is_not_verified_and_is_pending(): void
    {
        $payment = Payment::factory()->nmb()->create([
            'reference' => 'COTZ-PAY-20260805-000123',
            'amount' => 45000,
            'currency' => 'TZS',
        ]);

        $result = $this->mapper->fromResponse([
            'result' => 'SUCCESS',
            'response' => ['gatewayCode' => 'PENDING'],
            'order' => [
                'id' => 'COTZ-PAY-20260805-000123',
                'amount' => '45000.00',
                'currency' => 'TZS',
                'status' => 'AUTHENTICATION_INITIATED',
                'authenticationStatus' => 'AUTHENTICATION_PENDING',
                'totalAuthorizedAmount' => 0,
                'totalCapturedAmount' => 0,
            ],
            'transaction' => ['id' => 'TXN-PENDING-1'],
        ], $payment);

        $this->assertFalse($result->verified);
        $this->assertTrue($result->pending);
    }

    public function test_mpgs_transaction_list_selects_payment_and_verifies_capture(): void
    {
        $payment = Payment::factory()->nmb()->create([
            'reference' => 'COTZ-PAY-20260806-000050',
            'amount' => 3000,
            'currency' => 'TZS',
        ]);

        $result = $this->mapper->fromResponse([
            'result' => 'SUCCESS',
            'response' => ['gatewayCode' => 'APPROVED'],
            'id' => 'COTZ-PAY-20260806-000050',
            'amount' => '3000.00',
            'currency' => 'TZS',
            'status' => 'CAPTURED',
            'authenticationStatus' => 'AUTHENTICATION_SUCCESSFUL',
            'totalAuthorizedAmount' => '3000.00',
            'totalCapturedAmount' => '3000.00',
            'transaction' => [
                [
                    'transaction' => [
                        'id' => 'TXN-AUTHN-1',
                        'type' => 'AUTHENTICATION',
                        'result' => 'SUCCESS',
                    ],
                ],
                [
                    'transaction' => [
                        'id' => 'TXN-PAY-1',
                        'type' => 'PAYMENT',
                        'result' => 'SUCCESS',
                        'totalAuthorizedAmount' => '3000.00',
                        'totalCapturedAmount' => '3000.00',
                        'response' => [
                            'gatewayCode' => 'APPROVED',
                        ],
                    ],
                ],
            ],
        ], $payment);

        $this->assertTrue($result->verified);
        $this->assertFalse($result->pending);
        $this->assertSame('COTZ-PAY-20260806-000050', $result->orderId);
        $this->assertSame('TXN-PAY-1', $result->transactionId);
        $this->assertSame('successful', $result->rawResponse['nmb_outcome'] ?? null);
        $this->assertSame('PAYMENT', $result->rawResponse['nmb_outcome_context']['transaction_type'] ?? null);
        $this->assertSame('3000.00', $result->rawResponse['nmb_outcome_context']['total_captured_amount'] ?? null);
    }

    public function test_mpgs_payment_amounts_on_transaction_are_used_when_order_amounts_missing(): void
    {
        $payment = Payment::factory()->nmb()->create([
            'reference' => 'COTZ-PAY-20260806-000051',
            'amount' => 3000,
            'currency' => 'TZS',
        ]);

        $result = $this->mapper->fromResponse([
            'result' => 'SUCCESS',
            'response' => ['gatewayCode' => 'APPROVED'],
            'order' => [
                'id' => 'COTZ-PAY-20260806-000051',
                'currency' => 'TZS',
                'status' => 'CAPTURED',
                'authenticationStatus' => 'AUTHENTICATION_SUCCESSFUL',
            ],
            'transaction' => [
                [
                    'transaction' => [
                        'type' => 'AUTHENTICATION',
                        'result' => 'SUCCESS',
                    ],
                ],
                [
                    'transaction' => [
                        'id' => 'TXN-PAY-2',
                        'type' => 'PAYMENT',
                        'result' => 'SUCCESS',
                        'amount' => '3000.00',
                        'totalAuthorizedAmount' => '3000.00',
                        'totalCapturedAmount' => '3000.00',
                    ],
                ],
            ],
        ], $payment);

        $this->assertTrue($result->verified);
        $this->assertSame('TXN-PAY-2', $result->transactionId);
        $this->assertSame('3000.00', $result->rawResponse['nmb_outcome_context']['total_captured_amount'] ?? null);
    }

    public function test_rejects_gateway_failure(): void
    {
        $payment = Payment::factory()->nmb()->create([
            'reference' => 'PAY-2026-000123',
        ]);

        $result = $this->mapper->fromResponse([
            'result' => 'FAILURE',
            'error' => [
                'explanation' => 'Order not found.',
            ],
        ], $payment);

        $this->assertFalse($result->verified);
        $this->assertSame('Order not found.', $result->message);
    }
}
