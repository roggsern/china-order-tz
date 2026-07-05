<?php

namespace App\Actions\AdminPayments;

use App\Http\Requests\Admin\StorePaymentRequest;
use App\Models\Order;
use App\Models\Payment;

class CreatePaymentAction
{
    public function handle(StorePaymentRequest $request): Payment
    {
        $validated = $request->validated();
        $order = Order::query()->findOrFail($validated['order_id']);

        return Payment::query()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'method' => $validated['payment_method'],
            'status' => $validated['status'],
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'reference' => $validated['transaction_reference'] ?? null,
            'paid_at' => $validated['paid_at'] ?? null,
        ])->load(['order']);
    }
}
