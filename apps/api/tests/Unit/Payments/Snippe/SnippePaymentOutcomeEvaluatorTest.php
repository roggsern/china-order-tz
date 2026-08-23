<?php

namespace Tests\Unit\Payments\Snippe;

use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Payments\Gateways\Snippe\SnippePaymentOutcomeEvaluator;
use Tests\TestCase;

class SnippePaymentOutcomeEvaluatorTest extends TestCase
{
    private SnippePaymentOutcomeEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new SnippePaymentOutcomeEvaluator;
    }

    public function test_completed_maps_to_successful_when_amount_currency_and_reference_match(): void
    {
        $transaction = $this->transaction();

        $outcome = $this->evaluator->evaluate([
            'reference' => 'pi_test_ref',
            'status' => 'completed',
            'amount' => ['value' => 45000, 'currency' => 'TZS'],
            'metadata' => [
                'merchant_reference' => 'COTZ-PAY-20260823-000001',
                'payment_transaction_id' => $transaction->id,
            ],
        ], $transaction);

        $this->assertTrue($outcome->ok);
        $this->assertSame(PaymentTransactionStatus::Successful, $outcome->status);
    }

    public function test_wrong_amount_cannot_succeed(): void
    {
        $transaction = new PaymentTransaction([
            'merchant_reference' => 'COTZ-PAY-20260823-000001',
            'provider_reference' => 'pi_test_ref',
            'amount' => '45000.00',
            'currency' => 'TZS',
        ]);

        $outcome = $this->evaluator->evaluate([
            'reference' => 'pi_test_ref',
            'status' => 'completed',
            'amount' => ['value' => 44000, 'currency' => 'TZS'],
        ], $transaction);

        $this->assertFalse($outcome->ok);
        $this->assertSame(PaymentTransactionStatus::Processing, $outcome->status);
    }

    public function test_wrong_currency_cannot_succeed(): void
    {
        $transaction = new PaymentTransaction([
            'provider_reference' => 'pi_test_ref',
            'amount' => '45000.00',
            'currency' => 'TZS',
        ]);

        $outcome = $this->evaluator->evaluate([
            'reference' => 'pi_test_ref',
            'status' => 'completed',
            'amount' => ['value' => 45000, 'currency' => 'USD'],
        ], $transaction);

        $this->assertFalse($outcome->ok);
        $this->assertSame(PaymentTransactionStatus::Processing, $outcome->status);
    }

    public function test_wrong_reference_cannot_succeed(): void
    {
        $transaction = new PaymentTransaction([
            'provider_reference' => 'pi_expected',
            'amount' => '45000.00',
            'currency' => 'TZS',
        ]);

        $outcome = $this->evaluator->evaluate([
            'reference' => 'pi_other',
            'status' => 'completed',
            'amount' => ['value' => 45000, 'currency' => 'TZS'],
        ], $transaction);

        $this->assertFalse($outcome->ok);
        $this->assertSame(PaymentTransactionStatus::Processing, $outcome->status);
    }

    public function test_failed_maps_to_failed(): void
    {
        $transaction = new PaymentTransaction([
            'provider_reference' => 'pi_test_ref',
            'amount' => '45000.00',
            'currency' => 'TZS',
        ]);

        $outcome = $this->evaluator->evaluate([
            'reference' => 'pi_test_ref',
            'status' => 'failed',
            'failure_reason' => 'declined',
        ], $transaction);

        $this->assertFalse($outcome->ok);
        $this->assertSame(PaymentTransactionStatus::Failed, $outcome->status);
    }

    public function test_expired_maps_to_failed_with_reason_preserved_in_context(): void
    {
        $transaction = new PaymentTransaction([
            'provider_reference' => 'pi_test_ref',
            'amount' => '45000.00',
            'currency' => 'TZS',
        ]);

        $outcome = $this->evaluator->evaluate([
            'reference' => 'pi_test_ref',
            'status' => 'expired',
        ], $transaction);

        $this->assertFalse($outcome->ok);
        $this->assertSame(PaymentTransactionStatus::Failed, $outcome->status);
        $this->assertSame('expired', $outcome->context['provider_status']);
    }

    public function test_voided_maps_to_cancelled(): void
    {
        $transaction = new PaymentTransaction([
            'provider' => PaymentProvider::Snippe,
            'provider_reference' => 'pi_test_ref',
            'amount' => '45000.00',
            'currency' => 'TZS',
        ]);

        $outcome = $this->evaluator->evaluate([
            'reference' => 'pi_test_ref',
            'status' => 'voided',
        ], $transaction);

        $this->assertFalse($outcome->ok);
        $this->assertSame(PaymentTransactionStatus::Cancelled, $outcome->status);
    }

    public function test_provider_generated_external_reference_does_not_block_settlement(): void
    {
        $transaction = $this->transaction();

        $outcome = $this->evaluator->evaluate([
            'reference' => 'pi_test_ref',
            'external_reference' => 'S20388368013',
            'status' => 'completed',
            'amount' => ['value' => 45000, 'currency' => 'TZS'],
            'metadata' => [
                'merchant_reference' => 'COTZ-PAY-20260823-000001',
                'payment_transaction_id' => $transaction->id,
            ],
        ], $transaction);

        $this->assertTrue($outcome->ok);
        $this->assertSame(PaymentTransactionStatus::Successful, $outcome->status);
    }

    public function test_missing_optional_external_reference_remains_valid(): void
    {
        $transaction = $this->transaction();

        $outcome = $this->evaluator->evaluate([
            'reference' => 'pi_test_ref',
            'status' => 'completed',
            'amount' => ['value' => 45000, 'currency' => 'TZS'],
            'metadata' => [
                'merchant_reference' => 'COTZ-PAY-20260823-000001',
                'payment_transaction_id' => $transaction->id,
            ],
        ], $transaction);

        $this->assertTrue($outcome->ok);
        $this->assertSame(PaymentTransactionStatus::Successful, $outcome->status);
    }

    public function test_wrong_metadata_merchant_reference_cannot_succeed(): void
    {
        $transaction = $this->transaction();

        $outcome = $this->evaluator->evaluate([
            'reference' => 'pi_test_ref',
            'external_reference' => 'S20388368013',
            'status' => 'completed',
            'amount' => ['value' => 45000, 'currency' => 'TZS'],
            'metadata' => [
                'merchant_reference' => 'COTZ-PAY-OTHER',
                'payment_transaction_id' => $transaction->id,
            ],
        ], $transaction);

        $this->assertFalse($outcome->ok);
        $this->assertSame(PaymentTransactionStatus::Processing, $outcome->status);
        $this->assertSame('merchant_reference_mismatch', $outcome->context['reason']);
    }

    public function test_wrong_metadata_payment_transaction_id_cannot_succeed(): void
    {
        $transaction = $this->transaction();

        $outcome = $this->evaluator->evaluate([
            'reference' => 'pi_test_ref',
            'status' => 'completed',
            'amount' => ['value' => 45000, 'currency' => 'TZS'],
            'metadata' => [
                'merchant_reference' => 'COTZ-PAY-20260823-000001',
                'payment_transaction_id' => '00000000-0000-0000-0000-000000000099',
            ],
        ], $transaction);

        $this->assertFalse($outcome->ok);
        $this->assertSame(PaymentTransactionStatus::Processing, $outcome->status);
        $this->assertSame('payment_transaction_mismatch', $outcome->context['reason']);
    }

    public function test_wrong_provider_cannot_succeed(): void
    {
        $transaction = $this->transaction([
            'provider' => PaymentProvider::Nmb,
        ]);

        $outcome = $this->evaluator->evaluate([
            'reference' => 'pi_test_ref',
            'status' => 'completed',
            'amount' => ['value' => 45000, 'currency' => 'TZS'],
        ], $transaction);

        $this->assertFalse($outcome->ok);
        $this->assertSame(PaymentTransactionStatus::Processing, $outcome->status);
        $this->assertSame('provider_mismatch', $outcome->context['reason']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function transaction(array $overrides = []): PaymentTransaction
    {
        $transaction = new PaymentTransaction(array_merge([
            'provider' => PaymentProvider::Snippe,
            'merchant_reference' => 'COTZ-PAY-20260823-000001',
            'provider_reference' => 'pi_test_ref',
            'amount' => '45000.00',
            'currency' => 'TZS',
        ], $overrides));
        $transaction->id = '11111111-1111-1111-1111-111111111111';

        return $transaction;
    }
}
