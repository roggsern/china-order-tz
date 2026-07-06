<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

class CompletePaymentAction
{
    public function handle(Payment $payment): Order
    {
        if ($payment->status !== PaymentStatus::Completed) {
            $this->throwValidationError('Payment must be completed.');
        }

        $order = $payment->order()->firstOrFail();

        return $order->fresh()->load([
            'user',
            'coupon',
            'items.product',
            'items.variant',
            'payments',
            'shippingAddress',
        ]);
    }

    private function throwValidationError(string $message): never
    {
        throw ValidationException::withMessages([
            'payment' => [$message],
        ]);
    }
}
