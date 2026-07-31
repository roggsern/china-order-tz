<?php

namespace App\Services\Refunds;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundTransactionStatus;
use App\Events\Audit\RefundProcessedAudit;
use App\Events\Returns\RefundApproved;
use App\Events\Returns\RefundCompleted;
use App\Events\Returns\RefundFailed;
use App\Events\Returns\RefundRejected;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundTransaction;
use App\Services\Returns\RefundEngine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Finance refund operations workflow — approve, reject, process via provider abstraction.
 * Builds on RefundEngine without modifying PaymentOrchestrator.
 */
class RefundOperationsService
{
    public function __construct(
        private readonly RefundEngine $refundEngine,
        private readonly RefundProviderRegistry $providers,
    ) {}

    /**
     * @param  array{
     *     status?: string|null,
     *     order_id?: string|null,
     *     customer_id?: string|null,
     *     payment_id?: string|null,
     *     search?: string|null
     * }  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = RefundTransaction::query()
            ->with([
                'order.user',
                'order.payments',
                'returnRequest.customer',
                'customer',
                'payment',
                'createdByAdmin',
                'approvedByAdmin',
            ])
            ->latest('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }
        if (! empty($filters['order_id'])) {
            $query->where('order_id', (string) $filters['order_id']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', (string) $filters['customer_id']);
        }
        if (! empty($filters['payment_id'])) {
            $query->where('payment_id', (string) $filters['payment_id']);
        }
        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('reference', 'like', $term)
                    ->orWhere('provider_reference', 'like', $term)
                    ->orWhere('reason', 'like', $term)
                    ->orWhereHas('order', fn (Builder $q) => $q->where('order_number', 'like', $term));
            });
        }

        return $query->paginate(max(1, min($perPage, 100)));
    }

    public function show(RefundTransaction $refund): RefundTransaction
    {
        return $refund->load([
            'order.user',
            'order.payments',
            'returnRequest.customer',
            'customer',
            'payment',
            'createdByAdmin',
            'approvedByAdmin',
            'processedByAdmin',
            'rejectedByAdmin',
        ]);
    }

    /**
     * @param  array{
     *     order_id: string,
     *     payment_id?: string|null,
     *     amount: float|int|string,
     *     currency?: string|null,
     *     reason?: string|null,
     *     notes?: string|null,
     *     method?: string|null,
     *     return_request_id?: string|null
     * }  $input
     */
    public function create(array $input, Admin $admin): RefundTransaction
    {
        /** @var Order $order */
        $order = Order::query()->with(['payments', 'refundTransactions', 'user'])->findOrFail($input['order_id']);

        $this->assertOrderRefundable($order);

        $payment = null;
        if (! empty($input['payment_id'])) {
            $payment = Payment::query()
                ->whereKey($input['payment_id'])
                ->where('order_id', $order->id)
                ->first();

            if ($payment === null) {
                throw ValidationException::withMessages([
                    'payment_id' => ['Payment does not belong to this order.'],
                ]);
            }

            $this->assertPaymentRefundable($payment);
        }

        $this->assertNoOpenRefund($order, $input['return_request_id'] ?? null);

        $amount = number_format((float) $input['amount'], 2, '.', '');
        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Refund amount must be greater than zero.'],
            ]);
        }

        $remaining = $this->remainingRefundableAmount($order);
        if (bccomp($amount, $remaining, 2) > 0) {
            throw ValidationException::withMessages([
                'amount' => ["Refund amount cannot exceed remaining refundable amount ({$remaining})."],
            ]);
        }

        $refund = DB::transaction(function () use ($order, $payment, $input, $amount, $admin): RefundTransaction {
            return RefundTransaction::query()->create([
                'return_request_id' => $input['return_request_id'] ?? null,
                'order_id' => $order->id,
                'customer_id' => $order->user_id,
                'payment_id' => $payment?->id,
                'amount' => $amount,
                'currency' => strtoupper((string) ($input['currency'] ?? $order->currency ?? 'TZS')),
                'status' => RefundTransactionStatus::Requested,
                'method' => $input['method'] ?? ($payment?->method ?? 'manual'),
                'reference' => null,
                'notes' => $input['notes'] ?? null,
                'reason' => $input['reason'] ?? null,
                'created_by_admin_id' => $admin->id,
            ]);
        });

        try {
            event(new \App\Events\Returns\RefundCreated($refund, $admin));
        } catch (\Throwable $e) {
            Log::warning('refund_ops.created_event_failed', [
                'refund_id' => $refund->id,
                'message' => $e->getMessage(),
            ]);
        }

        return $this->show($refund);
    }

    public function approve(RefundTransaction $refund, Admin $admin, ?string $notes = null): RefundTransaction
    {
        return DB::transaction(function () use ($refund, $admin, $notes): RefundTransaction {
            $current = $this->resolveStatus($refund);

            if (in_array($current, [RefundTransactionStatus::Pending, RefundTransactionStatus::Requested], true)) {
                $refund = $this->transition($refund, RefundTransactionStatus::UnderReview, $admin, $notes);
                $refund->reviewed_at = now();
                $refund->save();
            }

            $refund = $this->transition($this->fresh($refund), RefundTransactionStatus::Approved, $admin, $notes);
            $refund->approved_by_admin_id = $admin->id;
            $refund->approved_at = now();
            $refund->save();

            event(new RefundApproved($refund, $admin));

            return $this->show($refund);
        });
    }

    public function reject(RefundTransaction $refund, Admin $admin, ?string $reason = null): RefundTransaction
    {
        return DB::transaction(function () use ($refund, $admin, $reason): RefundTransaction {
            $refund = $this->transition($this->fresh($refund), RefundTransactionStatus::Rejected, $admin, $reason);
            $refund->rejected_by_admin_id = $admin->id;
            $refund->rejected_at = now();
            if ($reason) {
                $refund->reason = trim(($refund->reason ? $refund->reason.' — ' : '').$reason);
            }
            $refund->save();

            event(new RefundRejected($refund, $admin, $reason));

            return $this->show($refund);
        });
    }

    public function process(RefundTransaction $refund, Admin $admin, ?string $notes = null): RefundTransaction
    {
        return DB::transaction(function () use ($refund, $admin, $notes): RefundTransaction {
            $current = $this->resolveStatus($refund);

            if ($current === RefundTransactionStatus::Approved) {
                $refund = $this->transition($this->fresh($refund), RefundTransactionStatus::Processing, $admin, $notes);
                $refund->processed_by_admin_id = $admin->id;
                $refund->processed_at = now();
                $refund->save();

                event(RefundProcessedAudit::fromRefund($refund, $admin));
            } elseif ($current !== RefundTransactionStatus::Processing) {
                throw ValidationException::withMessages([
                    'status' => ['Refund must be approved before processing.'],
                ]);
            }

            $provider = $this->providers->resolve($refund);

            if (! $provider->isAvailable($refund)) {
                $result = RefundProviderResult::unavailable('Refund provider is not available for this transaction.');
            } else {
                $result = $provider->process($this->fresh($refund), $admin);
            }

            if (! $result->success) {
                $refund = $this->transition($this->fresh($refund), RefundTransactionStatus::Failed, $admin, $result->failureReason ?? 'Provider refund failed.');
                $refund->failed_at = now();
                $refund->provider_response = $result->providerResponse;
                $refund->save();

                event(new RefundFailed($refund, $admin, $result->failureReason));

                return $this->show($refund);
            }

            if ($result->providerReference) {
                $refund->provider_reference = $result->providerReference;
            }
            $refund->provider_response = $result->providerResponse;
            $refund->save();

            $refund = $this->refundEngine->updateStatus($this->fresh($refund), [
                'status' => RefundTransactionStatus::Completed->value,
                'reference' => $result->providerReference ?? $refund->reference,
                'notes' => $notes ?? $refund->notes,
            ], $admin);

            $refund->completed_at = now();
            $refund->save();

            return $this->show($refund);
        });
    }

    public function remainingRefundableAmount(Order $order): string
    {
        $refundable = $this->refundEngine->refundableAmount($order);
        $refunded = RefundTransaction::query()
            ->where('order_id', $order->id)
            ->where('status', RefundTransactionStatus::Completed->value)
            ->sum('amount');

        $remaining = bcsub($refundable, number_format((float) $refunded, 2, '.', ''), 2);

        return bccomp($remaining, '0', 2) < 0 ? '0.00' : $remaining;
    }

    private function assertOrderRefundable(Order $order): void
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if (in_array($status, [OrderStatus::Cancelled, OrderStatus::PendingPayment], true)) {
            throw ValidationException::withMessages([
                'order_id' => ['Cannot refund cancelled or unpaid orders.'],
            ]);
        }

        if ($order->paid_at === null && ! $order->payments->contains(fn (Payment $p) => (
            ($p->status instanceof PaymentStatus ? $p->status : PaymentStatus::tryFrom((string) $p->status)) === PaymentStatus::Paid
        ))) {
            throw ValidationException::withMessages([
                'order_id' => ['Order has no successful payment to refund.'],
            ]);
        }
    }

    private function assertPaymentRefundable(Payment $payment): void
    {
        $status = $payment->status instanceof PaymentStatus
            ? $payment->status
            : PaymentStatus::tryFrom((string) $payment->status);

        if ($status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment_id' => ['Only paid payments can be refunded.'],
            ]);
        }
    }

    private function assertNoOpenRefund(Order $order, ?string $returnRequestId = null): void
    {
        $open = $order->refundTransactions
            ->when(
                $returnRequestId,
                fn ($collection) => $collection->where('return_request_id', $returnRequestId),
                fn ($collection) => $collection->whereNull('return_request_id'),
            )
            ->first(fn (RefundTransaction $refund) => ! $this->resolveStatus($refund)->isTerminal());

        if ($open !== null) {
            throw ValidationException::withMessages([
                'refund' => ['An open refund already exists for this order.'],
            ]);
        }
    }

    private function transition(
        RefundTransaction $refund,
        RefundTransactionStatus $next,
        Admin $admin,
        ?string $notes = null,
    ): RefundTransaction {
        return $this->refundEngine->updateStatus($refund, [
            'status' => $next->value,
            'notes' => $notes ?? $refund->notes,
        ], $admin);
    }

    private function resolveStatus(RefundTransaction $refund): RefundTransactionStatus
    {
        return $refund->status instanceof RefundTransactionStatus
            ? $refund->status
            : RefundTransactionStatus::tryFromMixed($refund->status) ?? RefundTransactionStatus::Requested;
    }

    private function fresh(RefundTransaction $refund): RefundTransaction
    {
        return RefundTransaction::query()->whereKey($refund->id)->firstOrFail();
    }
}
