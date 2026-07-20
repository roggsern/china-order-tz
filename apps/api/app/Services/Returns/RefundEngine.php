<?php

namespace App\Services\Returns;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundTransactionStatus;
use App\Enums\ReturnRequestStatus;
use App\Events\Returns\RefundCompleted;
use App\Events\Returns\RefundCreated;
use App\Models\Admin;
use App\Models\Order;
use App\Models\RefundTransaction;
use App\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Refund Engine — manual refund workflow (return approval + cancellation refund_pending).
 * No automatic PSP refund execution in this launch phase.
 */
class RefundEngine
{
    /**
     * @param  array{
     *     amount?: float|int|string|null,
     *     currency?: string|null,
     *     method?: string|null,
     *     reference?: string|null,
     *     notes?: string|null
     * }  $input
     */
    public function createForReturn(ReturnRequest $return, array $input = [], ?Admin $admin = null): RefundTransaction
    {
        $return->loadMissing(['items', 'order', 'refundTransactions']);

        $status = $return->status instanceof ReturnRequestStatus
            ? $return->status
            : ReturnRequestStatus::from((string) $return->status);

        if (! in_array($status, [
            ReturnRequestStatus::Approved,
            ReturnRequestStatus::Inspection,
            ReturnRequestStatus::Completed,
        ], true)) {
            throw ValidationException::withMessages([
                'return' => ['Refunds can only be created after the return is approved.'],
            ]);
        }

        $open = $return->refundTransactions
            ->first(fn (RefundTransaction $r) => ! in_array(
                $r->status instanceof RefundTransactionStatus ? $r->status->value : (string) $r->status,
                ['completed', 'failed'],
                true,
            ));

        if ($open !== null) {
            throw ValidationException::withMessages([
                'refund' => ['An open refund transaction already exists for this return.'],
            ]);
        }

        $amount = $input['amount'] ?? $return->items->sum(function (mixed $item) {
            return (float) ($item->refund_amount ?? 0);
        });

        if ((float) $amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Refund amount must be greater than zero.'],
            ]);
        }

        $refund = DB::transaction(function () use ($return, $input, $amount): RefundTransaction {
            return RefundTransaction::query()->create([
                'return_request_id' => $return->id,
                'order_id' => $return->order_id,
                'amount' => $amount,
                'currency' => strtoupper((string) ($input['currency'] ?? $return->order?->currency ?? 'TZS')),
                'status' => RefundTransactionStatus::Pending,
                'method' => $input['method'] ?? 'manual',
                'reference' => $input['reference'] ?? null,
                'notes' => $input['notes'] ?? null,
            ]);
        });

        try {
            event(new RefundCreated($refund, $admin));
        } catch (\Throwable $e) {
            Log::warning('refunds.event_created_failed', [
                'refund_id' => $refund->id,
                'message' => $e->getMessage(),
            ]);
        }

        return $refund->fresh(['returnRequest', 'order']) ?? $refund;
    }

    /**
     * @param  array{
     *     status: string,
     *     reference?: string|null,
     *     notes?: string|null,
     *     amount?: float|int|string|null
     * }  $input
     */
    public function updateStatus(RefundTransaction $refund, array $input, ?Admin $admin = null): RefundTransaction
    {
        $next = RefundTransactionStatus::tryFrom((string) ($input['status'] ?? ''));
        if ($next === null) {
            throw ValidationException::withMessages([
                'status' => ['Invalid refund status.'],
            ]);
        }

        return DB::transaction(function () use ($refund, $input, $next, $admin): RefundTransaction {
            /** @var RefundTransaction $locked */
            $locked = RefundTransaction::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            $current = $locked->status instanceof RefundTransactionStatus
                ? $locked->status
                : RefundTransactionStatus::from((string) $locked->status);

            $oldAmount = (string) $locked->amount;

            if (array_key_exists('amount', $input) && $input['amount'] !== null && $current === RefundTransactionStatus::Pending) {
                $locked->amount = $input['amount'];
            }
            if (array_key_exists('reference', $input)) {
                $locked->reference = $input['reference'];
            }
            if (array_key_exists('notes', $input)) {
                $locked->notes = $input['notes'];
            }

            if ($current !== $next) {
                if (! $current->canTransitionTo($next)) {
                    throw ValidationException::withMessages([
                        'status' => [
                            "Cannot transition refund from [{$current->value}] to [{$next->value}].",
                        ],
                    ]);
                }
                $locked->status = $next;
            }

            $locked->save();

            if ((string) $locked->amount !== $oldAmount) {
                $return = $locked->returnRequest ?? (
                    $locked->return_request_id
                        ? ReturnRequest::query()->find($locked->return_request_id)
                        : null
                );
                if ($return !== null) {
                    event(\App\Events\Audit\ReturnRefundAmountChanged::fromChange(
                        $return,
                        null,
                        $oldAmount,
                        (string) $locked->amount,
                        $admin,
                        $locked,
                    ));
                }
            }

            $fresh = $locked->fresh(['returnRequest.customer', 'order']) ?? $locked;

            if ($next === RefundTransactionStatus::Completed && $current !== $next) {
                try {
                    event(new RefundCompleted($fresh, $admin));
                } catch (\Throwable $e) {
                    Log::warning('refunds.event_completed_failed', [
                        'refund_id' => $fresh->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            return $fresh;
        });
    }

    /**
     * Ensure a pending cancellation RefundTransaction exists for a refund_pending order.
     * Idempotent: returns the existing open (non-terminal) cancellation refund when present.
     */
    public function ensureCancellationRefundPending(Order $order, ?Admin $admin = null): RefundTransaction
    {
        $order->loadMissing(['payments', 'refundTransactions']);

        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if ($status !== OrderStatus::RefundPending) {
            throw ValidationException::withMessages([
                'order' => ['Cancellation refunds require refund_pending status.'],
            ]);
        }

        $open = $order->refundTransactions
            ->filter(fn (RefundTransaction $r) => $r->return_request_id === null)
            ->first(fn (RefundTransaction $r) => ! in_array(
                $r->status instanceof RefundTransactionStatus ? $r->status->value : (string) $r->status,
                ['completed', 'failed'],
                true,
            ));

        if ($open !== null) {
            return $open;
        }

        $amount = $this->refundableAmount($order);

        $refund = DB::transaction(function () use ($order, $amount): RefundTransaction {
            return RefundTransaction::query()->create([
                'return_request_id' => null,
                'order_id' => $order->id,
                'amount' => $amount,
                'currency' => strtoupper((string) ($order->currency ?? 'TZS')),
                'status' => RefundTransactionStatus::Pending,
                'method' => 'manual_cancellation',
                'reference' => null,
                'notes' => 'Awaiting manual refund confirmation (cancellation).',
            ]);
        });

        try {
            event(new RefundCreated($refund, $admin));
        } catch (\Throwable $e) {
            Log::warning('refunds.cancellation_created_event_failed', [
                'refund_id' => $refund->id,
                'message' => $e->getMessage(),
            ]);
        }

        return $refund->fresh(['order']) ?? $refund;
    }

    /**
     * @param  array{
     *     amount: float|int|string,
     *     reference: string,
     *     notes?: string|null,
     *     reason?: string|null
     * }  $input
     */
    public function completeCancellationRefund(Order $order, array $input, Admin $admin): RefundTransaction
    {
        return DB::transaction(function () use ($order, $input, $admin): RefundTransaction {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing(['payments', 'refundTransactions']);

            $status = $locked->status instanceof OrderStatus
                ? $locked->status
                : OrderStatus::tryFrom((string) $locked->status);

            if ($status !== OrderStatus::RefundPending) {
                throw ValidationException::withMessages([
                    'order' => ['Order is not awaiting a cancellation refund.'],
                ]);
            }

            $completed = $locked->refundTransactions
                ->filter(fn (RefundTransaction $r) => $r->return_request_id === null)
                ->first(fn (RefundTransaction $r) => (
                    $r->status instanceof RefundTransactionStatus
                        ? $r->status
                        : RefundTransactionStatus::tryFrom((string) $r->status)
                ) === RefundTransactionStatus::Completed);

            if ($completed !== null) {
                // Idempotent replay of duplicate admin completion.
                return $completed->fresh(['order']) ?? $completed;
            }

            $refundable = $this->refundableAmount($locked);
            $amount = (string) $input['amount'];

            if (bccomp($amount, $refundable, 2) !== 0) {
                throw ValidationException::withMessages([
                    'amount' => [
                        "Launch supports full cancellation refunds only. Expected {$refundable}, got {$amount}.",
                    ],
                ]);
            }

            $refund = $this->ensureCancellationRefundPending($locked, $admin);

            /** @var RefundTransaction $lockedRefund */
            $lockedRefund = RefundTransaction::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if (($lockedRefund->status instanceof RefundTransactionStatus
                ? $lockedRefund->status
                : RefundTransactionStatus::from((string) $lockedRefund->status)) === RefundTransactionStatus::Completed
            ) {
                return $lockedRefund->fresh(['order']) ?? $lockedRefund;
            }

            $notes = trim(implode(' — ', array_filter([
                $input['reason'] ?? null,
                $input['notes'] ?? null,
            ])));

            $path = ['approved', 'processing', 'completed'];
            $current = $lockedRefund;
            foreach ($path as $step) {
                $current = $this->updateStatus($current, [
                    'status' => $step,
                    'reference' => $input['reference'],
                    'notes' => $notes !== '' ? $notes : ($current->notes ?? 'Manual cancellation refund confirmed.'),
                    'amount' => $amount,
                ], $admin);
            }

            return $current->fresh(['order']) ?? $current;
        });
    }

    /**
     * @param  array{notes?: string|null, reason?: string|null}  $input
     */
    public function failCancellationRefund(Order $order, array $input, Admin $admin): RefundTransaction
    {
        return DB::transaction(function () use ($order, $input, $admin): RefundTransaction {
            $refund = $this->ensureCancellationRefundPending($order, $admin);

            $notes = trim(implode(' — ', array_filter([
                $input['reason'] ?? null,
                $input['notes'] ?? null,
                'Manual cancellation refund marked failed — recoverable.',
            ])));

            return $this->updateStatus($refund, [
                'status' => 'failed',
                'notes' => $notes,
            ], $admin);
        });
    }

    public function refundableAmount(Order $order): string
    {
        $order->loadMissing('payments');

        $paid = $order->payments
            ->filter(function ($p) {
                $status = $p->status instanceof PaymentStatus
                    ? $p->status
                    : PaymentStatus::tryFrom((string) $p->status);

                return $status === PaymentStatus::Paid;
            })
            ->sum(fn ($p) => (float) $p->amount);

        if ($paid > 0) {
            return number_format($paid, 2, '.', '');
        }

        return number_format((float) ($order->total ?? $order->grand_total ?? 0), 2, '.', '');
    }
}
