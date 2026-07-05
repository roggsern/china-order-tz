<?php

namespace App\Services\Payments;

use App\Actions\AdminOrders\PayOrderAction;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\DataTransferObjects\Payments\PaymentResult;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MockPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly PayOrderAction $payOrderAction,
    ) {}

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
            $payment->update([
                'status' => PaymentStatus::Completed,
                'paid_at' => now(),
            ]);

            $this->payOrderAction->handle(
                $payment->order()->firstOrFail(),
            );

            return new PaymentResult(
                success: true,
                status: PaymentStatus::Completed->value,
                message: 'Payment processed successfully.',
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
        $exception = ValidationException::withMessages([
            'payment' => [$message],
        ]);

        $exception->response = response()->json([
            'success' => false,
            'message' => $message,
        ], 422);

        throw $exception;
    }
}
