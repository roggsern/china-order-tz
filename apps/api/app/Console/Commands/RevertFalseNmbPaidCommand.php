<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrective recovery for orders falsely marked Paid after incomplete NMB/MPGS verification.
 * Appends new history — does not delete prior audit evidence.
 */
class RevertFalseNmbPaidCommand extends Command
{
    protected $signature = 'payments:revert-false-nmb-paid
                            {--payment-transaction= : Payment transaction UUID}
                            {--order= : Order UUID}
                            {--force : Required for destructive correction}
                            {--confirm= : Must equal REVERT_FALSE_PAID_NMB}';

    protected $description = 'Revert a falsely paid NMB order/payment to pending_payment without deleting audit history';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $confirm = (string) ($this->option('confirm') ?? '');

        if (! $force || $confirm !== 'REVERT_FALSE_PAID_NMB') {
            $this->error('Refusing to run without --force --confirm=REVERT_FALSE_PAID_NMB');

            return self::FAILURE;
        }

        $transactionId = $this->option('payment-transaction');
        $orderId = $this->option('order');

        if (! filled($transactionId) && ! filled($orderId)) {
            $this->error('Provide --payment-transaction= or --order=');

            return self::FAILURE;
        }

        /** @var PaymentTransaction|null $transaction */
        $transaction = null;
        if (filled($transactionId)) {
            $transaction = PaymentTransaction::query()->with('order')->find($transactionId);
        } elseif (filled($orderId)) {
            $transaction = PaymentTransaction::query()
                ->with('order')
                ->where('order_id', $orderId)
                ->where('provider', PaymentProvider::Nmb)
                ->orderByDesc('created_at')
                ->first();
        }

        if ($transaction === null) {
            $this->error('Payment transaction not found.');

            return self::FAILURE;
        }

        $provider = $transaction->provider instanceof PaymentProvider
            ? $transaction->provider
            : PaymentProvider::tryFrom((string) $transaction->provider);

        if ($provider !== PaymentProvider::Nmb) {
            $this->error('Transaction is not an NMB payment.');

            return self::FAILURE;
        }

        if ($transaction->status !== PaymentTransactionStatus::Successful) {
            $this->error('Transaction is not Successful; nothing to revert.');

            return self::FAILURE;
        }

        $order = $transaction->order;
        if ($order === null) {
            $this->error('Order missing for payment transaction.');

            return self::FAILURE;
        }

        $this->warn('About to revert false paid state:');
        $this->line('  payment_transaction_id: '.$transaction->id);
        $this->line('  order_id: '.$order->id);
        $this->line('  order_number: '.$order->order_number);
        $this->line('  merchant_reference: '.$transaction->merchant_reference);
        $this->line('  current order status: '.($order->status?->value ?? $order->status));
        $this->line('  current payment status: '.($transaction->status?->value ?? $transaction->status));

        DB::transaction(function () use ($transaction, $order): void {
            /** @var PaymentTransaction $lockedTxn */
            $lockedTxn = PaymentTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $fromOrderStatus = $lockedOrder->status instanceof OrderStatus
                ? $lockedOrder->status
                : OrderStatus::from((string) $lockedOrder->status);

            $lockedTxn->fill([
                'status' => PaymentTransactionStatus::Processing,
                'completed_at' => null,
                'verification_payload' => array_merge($lockedTxn->verification_payload ?? [], [
                    'false_paid_reverted_at' => now()->toIso8601String(),
                    'false_paid_revert_reason' => 'Incomplete MPGS authentication/settlement was incorrectly treated as paid.',
                ]),
            ])->save();

            if ($fromOrderStatus === OrderStatus::Paid) {
                $lockedOrder->fill([
                    'status' => OrderStatus::PendingPayment,
                    'paid_at' => null,
                ])->save();

                OrderStatusHistory::query()->create([
                    'order_id' => $lockedOrder->id,
                    'previous_status' => $fromOrderStatus->value,
                    'new_status' => OrderStatus::PendingPayment->value,
                    'notes' => 'Recovery: revert false NMB paid state (authentication/settlement incomplete)',
                    'source' => 'payments:revert-false-nmb-paid',
                    'actor_type' => 'system',
                    'idempotency_key' => 'revert-false-nmb-paid:'.$lockedTxn->id.':'.now()->timestamp,
                    'metadata' => [
                        'payment_transaction_id' => $lockedTxn->id,
                        'merchant_reference' => $lockedTxn->merchant_reference,
                        'note' => 'Prior Paid history rows are retained for audit.',
                    ],
                ]);
            }
        });

        $this->info('Reverted payment to processing and order to pending_payment (if it was paid).');
        $this->comment('Prior order_status_history / activity_logs were not deleted.');
        $this->comment('Customer can retry Hosted Checkout via the fresh-session endpoint.');

        return self::SUCCESS;
    }
}
