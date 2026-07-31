<?php

namespace App\Listeners\Storefront;

use App\Events\Audit\PaymentConfirmed;
use App\Models\Order;
use App\Services\Storefront\StorefrontEventService;
use Illuminate\Support\Facades\Log;

class HandleStorefrontLifecycle
{
    public function __construct(
        private readonly StorefrontEventService $events,
    ) {}

    public function onPaymentConfirmed(PaymentConfirmed $event): void
    {
        $order = Order::query()->find($event->subjectId());
        if ($order === null) {
            return;
        }

        try {
            $this->events->recordOrderCompleted($order);
        } catch (\Throwable $e) {
            Log::warning('storefront.order_completed_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
