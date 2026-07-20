<?php

namespace App\Listeners\Loyalty;

use App\Events\Audit\PaymentConfirmed;
use App\Models\Order;
use App\Services\Loyalty\LoyaltyEngine;
use Illuminate\Support\Facades\Log;

class HandleLoyaltyLifecycle
{
    public function __construct(
        private readonly LoyaltyEngine $loyalty,
    ) {}

    public function onPaymentConfirmed(PaymentConfirmed $event): void
    {
        $order = Order::query()->find($event->subjectId());
        if ($order === null) {
            return;
        }

        try {
            $this->loyalty->earnForPaidOrder($order);
        } catch (\Throwable $e) {
            Log::warning('loyalty.earn_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
