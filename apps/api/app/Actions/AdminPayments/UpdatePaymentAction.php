<?php

namespace App\Actions\AdminPayments;

use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\ManualPaymentMutationGuard;

class UpdatePaymentAction
{
    public function __construct(
        private readonly ManualPaymentMutationGuard $mutationGuard,
    ) {}

    public function handle(UpdatePaymentRequest $request, Payment $payment): Payment
    {
        $validated = $request->validated();
        $this->mutationGuard->assertDoesNotCreatePaidState($validated, $payment);
        $order = Order::query()->findOrFail($validated['order_id']);

        $attributes = $this->mutationGuard->attributesSafeForPersistence([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'method' => $validated['payment_method'],
            'status' => $validated['status'],
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'reference' => $validated['transaction_reference'] ?? null,
            'paid_at' => $validated['paid_at'] ?? null,
        ], $payment);

        $payment->update($attributes);

        return $payment->fresh()->load(['order']);
    }
}
