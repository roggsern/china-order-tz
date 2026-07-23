<?php

namespace App\Services\Payments\Orchestration;

use App\Enums\OrderStatus;
use App\Enums\NotificationEventType;
use App\Enums\PaymentTransactionStatus;
use App\Events\Audit\PaymentConfirmed as PaymentConfirmedAudit;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\CostProfit\ProfitEngine;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Inventory\DTOs\InventoryCommitmentContext;
use App\Services\Inventory\InventoryCommitmentService;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Marks orchestrator payment transactions (and parent orders) as paid.
 * Commits inventory once on payment success (ADR 055), then fulfillment.
 */
class PaymentTransactionCompletionService
{
    public function __construct(
        private readonly FulfillmentEngine $fulfillmentEngine,
        private readonly NotificationPlatform $notifications,
        private readonly ProfitEngine $profitEngine,
        private readonly OrderLifecycleEngine $lifecycle,
        private readonly InventoryCommitmentService $inventoryCommitment,
    ) {}

    public function applyResult(PaymentTransaction $transaction, PaymentProviderResult $result): PaymentTransaction
    {
        return DB::transaction(function () use ($transaction, $result): PaymentTransaction {
            /** @var PaymentTransaction $locked */
            $locked = PaymentTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotent: already successful — ensure commitment + fulfillment.
            if ($locked->status === PaymentTransactionStatus::Successful) {
                $locked->loadMissing('order');
                if ($locked->order !== null) {
                    $this->commitInventory($locked->order, $locked, strict: false);
                    $this->startFulfillment($locked->order);
                }

                return $locked->load('order');
            }

            $locked->fill([
                'provider_reference' => $result->providerReference ?? $locked->provider_reference,
                'external_transaction_id' => $result->externalTransactionId ?? $locked->external_transaction_id,
                'checkout_url' => $result->checkoutUrl ?? $locked->checkout_url,
                'success_indicator' => $result->successIndicator ?? $locked->success_indicator,
                'status' => $result->status,
                'request_payload' => $result->requestPayload ?? $locked->request_payload,
                'response_payload' => $result->responsePayload ?? $locked->response_payload,
                'verification_payload' => $result->verificationPayload ?? $locked->verification_payload,
                'completed_at' => $result->status === PaymentTransactionStatus::Successful
                    ? ($locked->completed_at ?? now())
                    : $locked->completed_at,
            ])->save();

            if ($result->status === PaymentTransactionStatus::Successful) {
                $this->markOrderPaid($locked);
            }

            return $locked->fresh(['order']) ?? $locked;
        });
    }

    private function markOrderPaid(PaymentTransaction $transaction): void
    {
        /** @var Order|null $order */
        $order = Order::query()
            ->whereKey($transaction->order_id)
            ->lockForUpdate()
            ->first();

        if ($order === null) {
            return;
        }

        $alreadyPaid = $order->status === OrderStatus::Paid && $order->paid_at !== null;

        if (! $alreadyPaid) {
            try {
                $this->lifecycle->markPaid(
                    $order,
                    OrderLifecycleContext::payment(
                        'Payment transaction successful',
                        'payment-txn:'.$transaction->id,
                        [
                            'payment_transaction_id' => $transaction->id,
                            'provider' => $transaction->provider instanceof \BackedEnum
                                ? $transaction->provider->value
                                : (string) $transaction->provider,
                        ],
                    ),
                );
            } catch (ValidationException $e) {
                Log::warning('lifecycle.mark_paid_rejected', [
                    'order_id' => $order->id,
                    'transaction_id' => $transaction->id,
                    'errors' => $e->errors(),
                ]);

                return;
            }

            $order = $order->fresh() ?? $order;

            try {
                event(PaymentConfirmedAudit::fromOrder($order));
            } catch (\Throwable $e) {
                Log::warning('audit.payment_confirmed_failed', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }

            $order->loadMissing('user');
            if ($order->user !== null) {
                try {
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
                    );
                } catch (\Throwable $e) {
                    Log::warning('notification.payment_confirmed_failed', [
                        'order_id' => $order->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        $paidOrder = $order->fresh() ?? $order;

        // Strict on first payment success path (including already-paid race): must commit.
        $this->commitInventory($paidOrder, $transaction, strict: true);

        try {
            $this->profitEngine->calculateForOrder($paidOrder);
        } catch (\Throwable $e) {
            Log::warning('profit.calculate_after_payment_failed', [
                'order_id' => $paidOrder->id,
                'message' => $e->getMessage(),
            ]);
        }

        $this->startFulfillment($paidOrder);
    }

    private function commitInventory(Order $order, PaymentTransaction $transaction, bool $strict): void
    {
        $this->inventoryCommitment->commitForOrder(new InventoryCommitmentContext(
            order: $order,
            payment: $transaction,
            source: 'payment_transaction',
            channel: null,
            metadata: [
                'payment_transaction_id' => $transaction->id,
            ],
            strict: $strict,
        ));
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
