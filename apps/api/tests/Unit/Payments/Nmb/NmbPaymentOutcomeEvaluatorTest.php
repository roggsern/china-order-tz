<?php

namespace Tests\Unit\Payments\Nmb;

use App\Payments\Gateways\Nmb\NmbPaymentOutcome;
use App\Payments\Gateways\Nmb\NmbPaymentOutcomeEvaluator;
use Tests\TestCase;

class NmbPaymentOutcomeEvaluatorTest extends TestCase
{
    private NmbPaymentOutcomeEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new NmbPaymentOutcomeEvaluator;
    }

    public function test_production_pending_authentication_payload_is_processing_not_paid(): void
    {
        $result = $this->evaluator->evaluate(
            [
                'result' => 'SUCCESS',
                'response' => [
                    'gatewayCode' => 'PENDING',
                ],
                'order' => [
                    'id' => 'COTZ-PAY-20260805-000123',
                    'amount' => '45000.00',
                    'currency' => 'TZS',
                    'status' => 'AUTHENTICATION_INITIATED',
                    'authenticationStatus' => 'AUTHENTICATION_PENDING',
                    'totalAuthorizedAmount' => 0,
                    'totalCapturedAmount' => 0,
                ],
                'transaction' => [
                    'id' => 'TXN-PENDING-1',
                ],
            ],
            expectedOrderId: 'COTZ-PAY-20260805-000123',
            expectedAmount: '45000.00',
            expectedCurrency: 'TZS',
        );

        $this->assertSame(NmbPaymentOutcome::Processing, $result->outcome);
        $this->assertFalse($result->outcome->isVerifiedPaid());
    }

    public function test_authorized_final_response_is_successful(): void
    {
        $result = $this->evaluator->evaluate(
            [
                'result' => 'SUCCESS',
                'response' => ['gatewayCode' => 'APPROVED'],
                'order' => [
                    'id' => 'COTZ-PAY-1',
                    'amount' => '10000.00',
                    'currency' => 'TZS',
                    'status' => 'AUTHORIZED',
                    'authenticationStatus' => 'AUTHENTICATION_SUCCESSFUL',
                    'totalAuthorizedAmount' => '10000.00',
                    'totalCapturedAmount' => '0.00',
                ],
                'transaction' => [
                    'id' => 'TXN-AUTH-1',
                    'type' => 'AUTHORIZATION',
                    'result' => 'SUCCESS',
                ],
            ],
            'COTZ-PAY-1',
            '10000.00',
            'TZS',
        );

        $this->assertSame(NmbPaymentOutcome::Successful, $result->outcome);
    }

    public function test_captured_response_is_successful(): void
    {
        $result = $this->evaluator->evaluate(
            [
                'result' => 'SUCCESS',
                'response' => ['gatewayCode' => 'APPROVED'],
                'order' => [
                    'id' => 'COTZ-PAY-2',
                    'amount' => '25000.00',
                    'currency' => 'TZS',
                    'status' => 'CAPTURED',
                    'authenticationStatus' => 'AUTHENTICATION_SUCCESSFUL',
                    'totalAuthorizedAmount' => '25000.00',
                    'totalCapturedAmount' => '25000.00',
                ],
                'transaction' => [
                    'id' => 'TXN-CAP-1',
                    'type' => 'PAYMENT',
                    'result' => 'SUCCESS',
                ],
            ],
            'COTZ-PAY-2',
            '25000.00',
            'TZS',
        );

        $this->assertSame(NmbPaymentOutcome::Successful, $result->outcome);
    }

    public function test_declined_response_is_failed(): void
    {
        $result = $this->evaluator->evaluate(
            [
                'result' => 'SUCCESS',
                'response' => ['gatewayCode' => 'DECLINED'],
                'order' => [
                    'id' => 'COTZ-PAY-3',
                    'amount' => '10000.00',
                    'currency' => 'TZS',
                    'status' => 'FAILED',
                    'totalAuthorizedAmount' => 0,
                    'totalCapturedAmount' => 0,
                ],
            ],
            'COTZ-PAY-3',
            '10000.00',
            'TZS',
        );

        $this->assertSame(NmbPaymentOutcome::Failed, $result->outcome);
    }

    public function test_mismatched_order_id_is_failed(): void
    {
        $result = $this->evaluator->evaluate(
            [
                'result' => 'SUCCESS',
                'response' => ['gatewayCode' => 'APPROVED'],
                'order' => [
                    'id' => 'WRONG-ID',
                    'amount' => '10000.00',
                    'currency' => 'TZS',
                    'status' => 'CAPTURED',
                    'totalAuthorizedAmount' => '10000.00',
                    'totalCapturedAmount' => '10000.00',
                ],
            ],
            'COTZ-PAY-EXPECTED',
            '10000.00',
            'TZS',
        );

        $this->assertSame(NmbPaymentOutcome::Failed, $result->outcome);
        $this->assertStringContainsString('order id', strtolower($result->message));
    }

    public function test_mismatched_currency_is_failed(): void
    {
        $result = $this->evaluator->evaluate(
            [
                'result' => 'SUCCESS',
                'response' => ['gatewayCode' => 'APPROVED'],
                'order' => [
                    'id' => 'COTZ-PAY-4',
                    'amount' => '10000.00',
                    'currency' => 'USD',
                    'status' => 'CAPTURED',
                    'totalAuthorizedAmount' => '10000.00',
                    'totalCapturedAmount' => '10000.00',
                ],
            ],
            'COTZ-PAY-4',
            '10000.00',
            'TZS',
        );

        $this->assertSame(NmbPaymentOutcome::Failed, $result->outcome);
    }

    public function test_insufficient_authorized_amount_is_processing(): void
    {
        $result = $this->evaluator->evaluate(
            [
                'result' => 'SUCCESS',
                'response' => ['gatewayCode' => 'APPROVED'],
                'order' => [
                    'id' => 'COTZ-PAY-5',
                    'amount' => '10000.00',
                    'currency' => 'TZS',
                    'status' => 'AUTHORIZED',
                    'totalAuthorizedAmount' => '5000.00',
                    'totalCapturedAmount' => '0.00',
                ],
            ],
            'COTZ-PAY-5',
            '10000.00',
            'TZS',
        );

        $this->assertSame(NmbPaymentOutcome::Processing, $result->outcome);
    }

    public function test_payer_interaction_required_is_processing(): void
    {
        $result = $this->evaluator->evaluate(
            [
                'result' => 'SUCCESS',
                'response' => ['gatewayCode' => 'PENDING'],
                'interaction' => ['payerInteraction' => 'REQUIRED'],
                'order' => [
                    'id' => 'COTZ-PAY-6',
                    'amount' => '10000.00',
                    'currency' => 'TZS',
                    'status' => 'AUTHENTICATION_INITIATED',
                    'authenticationStatus' => 'AUTHENTICATION_PENDING',
                    'totalAuthorizedAmount' => 0,
                    'totalCapturedAmount' => 0,
                ],
            ],
            'COTZ-PAY-6',
            '10000.00',
            'TZS',
        );

        $this->assertSame(NmbPaymentOutcome::Processing, $result->outcome);
    }
}
