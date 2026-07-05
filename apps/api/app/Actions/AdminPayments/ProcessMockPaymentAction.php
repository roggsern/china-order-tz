<?php

namespace App\Actions\AdminPayments;

use App\Actions\AdminOrders\PayOrderAction;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessMockPaymentAction
{
    public function __construct(
        private readonly PayOrderAction $payOrderAction,
    ) {}

    /**
     * @return array{payment?: Payment, order?: Order, failed: bool}
     */
    public function handle(Payment $payment, string $result): array
    {
        if ($payment->status !== PaymentStatus::Pending) {
            $this->throwValidationError('Only pending payments can be processed.');
        }

        if ($result === 'failed') {
            $payment->update([
                'status' => PaymentStatus::Failed,
            ]);

            return ['failed' => true];
        }

        return DB::transaction(function () use ($payment) {
            $payment->update([
                'status' => PaymentStatus::Completed,
                'paid_at' => now(),
            ]);

            $order = $this->payOrderAction->handle(
                $payment->order()->firstOrFail(),
            );

            return [
                'failed' => false,
                'payment' => $payment->fresh()->load(['order']),
                'order' => $order,
            ];
        });
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
