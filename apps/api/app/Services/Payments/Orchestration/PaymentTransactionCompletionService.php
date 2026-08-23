<?php

namespace App\Services\Payments\Orchestration;

use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use App\Services\Payments\PaidOrderCompletionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks orchestrator payment transactions (and parent orders) as paid.
 * Downstream paid processing is shared with manual confirmation.
 */
class PaymentTransactionCompletionService
{
    public function __construct(
        private readonly PaidOrderCompletionService $paidCompletion,
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
                    $this->completeOrder($locked, $locked->order, inventoryStrict: false);
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

            if ($result->status !== PaymentTransactionStatus::Successful || ! $result->ok) {
                // Never mark the order paid unless the provider reports a strict Successful outcome.
                return $locked->fresh(['order']) ?? $locked;
            }

            $this->markOrderPaid($locked);

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

        $this->completeOrder($transaction, $order, inventoryStrict: true);
    }

    private function completeOrder(
        PaymentTransaction $transaction,
        Order $order,
        bool $inventoryStrict,
    ): void {
        $context = OrderLifecycleContext::payment(
            'Payment transaction successful',
            'payment-txn:'.$transaction->id,
            [
                'payment_transaction_id' => $transaction->id,
                'provider' => $transaction->provider instanceof \BackedEnum
                    ? $transaction->provider->value
                    : (string) $transaction->provider,
            ],
        );

        try {
            $this->paidCompletion->complete(
                $order,
                $context,
                inventorySource: 'payment_transaction',
                inventoryPaymentTransaction: $transaction,
                inventoryStrict: $inventoryStrict,
                inventoryMetadata: [
                    'payment_transaction_id' => $transaction->id,
                ],
            );
        } catch (ValidationException) {
            // Gateway path historically logs and leaves the transaction result persisted.
        }
    }
}
