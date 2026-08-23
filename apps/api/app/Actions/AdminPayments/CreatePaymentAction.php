<?php

namespace App\Actions\AdminPayments;

use App\Http\Requests\Admin\StorePaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\ManualPaymentMutationGuard;

class CreatePaymentAction
{
    public function __construct(
        private readonly ManualPaymentMutationGuard $mutationGuard,
    ) {}

    public function handle(StorePaymentRequest $request): Payment
    {
        $validated = $request->validated();
        $this->mutationGuard->assertDoesNotCreatePaidState($validated);
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
        ]);

        return Payment::query()->create($attributes)->load(['order']);
    }
}
