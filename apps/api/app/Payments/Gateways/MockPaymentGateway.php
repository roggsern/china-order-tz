<?php

namespace App\Payments\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Contracts\PaymentGatewayInterface;
use App\Payments\Results\PaymentResult;
use App\Payments\ValueObjects\TransactionReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MockPaymentGateway implements PaymentGatewayInterface
{
    public function process(Payment $payment): PaymentResult
    {
        if ($payment->status !== PaymentStatus::Pending) {
            $this->throwValidationError('Only pending payments can be processed.');
        }

        $result = $payment->metadata['mock_result'] ?? 'success';

        if ($result === 'failed') {
            $payment->update([
                'status' => PaymentStatus::Failed,
            ]);

            return new PaymentResult(
                success: false,
                status: PaymentStatus::Failed->value,
                message: 'Payment failed.',
            );
        }

        return DB::transaction(function () use ($payment) {
            $transactionReference = TransactionReference::fromNullable(
                (string) Str::uuid(),
            );

            $payment->update([
                'status' => PaymentStatus::Completed,
                'paid_at' => now(),
                'reference' => $transactionReference?->value(),
            ]);

            return new PaymentResult(
                success: true,
                status: PaymentStatus::Completed->value,
                message: 'Payment processed successfully.',
                transactionReference: $transactionReference,
            );
        });
    }

    public function refund(Payment $payment): PaymentResult
    {
        return new PaymentResult(
            success: false,
            status: PaymentStatus::Failed->value,
            message: 'Refund is not supported by the mock payment gateway.',
        );
    }

    private function throwValidationError(string $message): never
    {
        throw ValidationException::withMessages([
            'payment' => [$message],
        ]);
    }
}
