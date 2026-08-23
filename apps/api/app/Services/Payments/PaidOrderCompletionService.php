<?php

namespace App\Services\Payments;

use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Events\Audit\PaymentConfirmed as PaymentConfirmedAudit;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\CostProfit\ProfitEngine;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Inventory\DTOs\InventoryCommitmentContext;
use App\Services\Inventory\InventoryCommitmentService;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Shared paid-order downstream path used by gateway and manual confirmation.
 * Does not invent a second paid writer — OrderLifecycleEngine remains authoritative.
 */
class PaidOrderCompletionService
{
    public function __construct(
        private readonly FulfillmentEngine $fulfillmentEngine,
        private readonly NotificationPlatform $notifications,
        private readonly ProfitEngine $profitEngine,
        private readonly OrderLifecycleEngine $lifecycle,
        private readonly InventoryCommitmentService $inventoryCommitment,
    ) {}

    /**
     * @param  array<string, mixed>  $inventoryMetadata
     */
    public function complete(
        Order $order,
        OrderLifecycleContext $context,
        string $inventorySource,
        ?PaymentTransaction $inventoryPaymentTransaction = null,
        ?Admin $inventoryActor = null,
        bool $inventoryStrict = true,
        array $inventoryMetadata = [],
    ): Order {
        $alreadyPaid = $order->status === OrderStatus::Paid && $order->paid_at !== null;

        if (! $alreadyPaid) {
            try {
                $this->lifecycle->markPaid($order, $context);
            } catch (ValidationException $e) {
                Log::warning('lifecycle.mark_paid_rejected', [
                    'order_id' => $order->id,
                    'source' => $context->source,
                    'errors' => $e->errors(),
                ]);

                throw $e;
            }

            $order = $order->fresh() ?? $order;
            $this->dispatchPaymentConfirmed($order);
        }

        $paidOrder = $order->fresh() ?? $order;

        $this->inventoryCommitment->commitForOrder(new InventoryCommitmentContext(
            order: $paidOrder,
            payment: $inventoryPaymentTransaction,
            actor: $inventoryActor,
            source: $inventorySource,
            metadata: $inventoryMetadata,
            strict: $alreadyPaid ? false : $inventoryStrict,
        ));

        try {
            $this->profitEngine->calculateForOrder($paidOrder, $inventoryActor);
        } catch (\Throwable $e) {
            Log::warning('profit.calculate_after_payment_failed', [
                'order_id' => $paidOrder->id,
                'message' => $e->getMessage(),
            ]);
        }

        $this->startFulfillment($paidOrder);

        return $paidOrder->fresh() ?? $paidOrder;
    }

    private function dispatchPaymentConfirmed(Order $order): void
    {
        try {
            event(PaymentConfirmedAudit::fromOrder($order));
        } catch (\Throwable $e) {
            Log::warning('audit.payment_confirmed_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }

        $order->loadMissing('user');
        if ($order->user === null) {
            return;
        }

        try {
            $notifyKey = 'payment_confirmed:'.$order->id.':'.$order->user->id;
            $this->notifications->notifyCustomer(
                NotificationEventType::PaymentConfirmed,
                $order->user,
                [
                    'customer_name' => $order->user->name,
                    'order_number' => $order->order_number,
                    'order_id' => $order->id,
                    'order_total' => (string) $order->total,
                    'currency' => $order->currency,
                ],
                idempotencyKey: $notifyKey,
                correlationKey: $notifyKey,
            );
        } catch (\Throwable $e) {
            Log::warning('notification.payment_confirmed_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function startFulfillment(Order $order): void
    {
        try {
            $this->fulfillmentEngine->createForOrder($order);
        } catch (\Throwable $e) {
            Log::warning('fulfillment.create_after_payment_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
