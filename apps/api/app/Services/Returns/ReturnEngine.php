<?php

namespace App\Services\Returns;

use App\Enums\InventoryDisposition;
use App\Enums\ReturnItemResolution;
use App\Enums\ReturnRequestStatus;
use App\Events\Returns\ReturnApproved;
use App\Events\Returns\ReturnCompleted;
use App\Events\Returns\ReturnRejected;
use App\Events\Returns\ReturnRequested;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Inventory\ReturnInventoryRestorationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Returns Engine — controlled return workflow.
 *
 * Online inventory restoration runs only on a real transition into Completed
 * (RC1-G3), via ReturnInventoryRestorationService + MutationGate.
 * Does not refund automatically; refunds remain a separate engine concern.
 */
class ReturnEngine
{
    public function __construct(
        private readonly ReturnEligibilityService $eligibility,
        private readonly ReturnInventoryRestorationService $inventoryRestoration,
    ) {}

    /**
     * @param  array{
     *     reason: string,
     *     description?: string|null,
     *     customer_notes?: string|null,
     *     items: list<array{
     *         order_item_id: string,
     *         quantity: int,
     *         reason?: string|null,
     *         replacement_requested?: bool
     *     }>
     * }  $input
     */
    public function requestReturn(User $customer, Order $order, array $input): ReturnRequest
    {
        if ($order->user_id !== $customer->id) {
            abort(404);
        }

        $this->eligibility->assertEligible($order);

        $items = $input['items'] ?? [];
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['At least one return item is required.'],
            ]);
        }

        $return = DB::transaction(function () use ($customer, $order, $input, $items): ReturnRequest {
            $return = ReturnRequest::query()->create([
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'status' => ReturnRequestStatus::Requested,
                'reason' => $input['reason'],
                'description' => $input['description'] ?? null,
                'customer_notes' => $input['customer_notes'] ?? null,
            ]);

            foreach ($items as $row) {
                /** @var OrderItem $orderItem */
                $orderItem = OrderItem::query()->whereKey($row['order_item_id'])->firstOrFail();
                $qty = (int) $row['quantity'];
                $this->eligibility->assertItemBelongs($order, $orderItem, $qty);

                $unit = (float) ($orderItem->unit_price_snapshot ?? $orderItem->unit_price ?? 0);
                $suggested = round($unit * $qty, 2);

                ReturnItem::query()->create([
                    'return_request_id' => $return->id,
                    'order_item_id' => $orderItem->id,
                    'quantity' => $qty,
                    'reason' => $row['reason'] ?? $input['reason'],
                    'replacement_requested' => (bool) ($row['replacement_requested'] ?? false),
                    'refund_amount' => $suggested,
                ]);
            }

            return $return->load(['items.orderItem', 'order', 'customer']);
        });

        try {
            event(new ReturnRequested($return));
        } catch (\Throwable $e) {
            Log::warning('returns.event_requested_failed', [
                'return_id' => $return->id,
                'message' => $e->getMessage(),
            ]);
        }

        return $return;
    }

    /**
     * @param  array{
     *     status: string,
     *     admin_notes?: string|null,
     *     items?: list<array{
     *         id: string,
     *         condition?: string|null,
     *         resolution?: string|null,
     *         refund_amount?: float|int|string|null,
     *         inventory_disposition?: string|null
     *     }>
     * }  $input
     */
    public function updateStatus(ReturnRequest $return, array $input, ?Admin $admin = null): ReturnRequest
    {
        $next = ReturnRequestStatus::tryFrom((string) ($input['status'] ?? ''));
        if ($next === null) {
            throw ValidationException::withMessages([
                'status' => ['Invalid return status.'],
            ]);
        }

        return DB::transaction(function () use ($return, $input, $next, $admin): ReturnRequest {
            /** @var ReturnRequest $locked */
            $locked = ReturnRequest::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();

            $current = $locked->status instanceof ReturnRequestStatus
                ? $locked->status
                : ReturnRequestStatus::from((string) $locked->status);

            // Already at target status: allow notes/item edits, never re-trigger inventory restore.
            if ($current === $next) {
                if (array_key_exists('admin_notes', $input)) {
                    $locked->admin_notes = $input['admin_notes'];
                    $locked->save();
                }
                $this->applyItemDecisions($locked, $input['items'] ?? [], $admin);

                return $locked->fresh(['items.orderItem', 'order.user', 'customer', 'approver', 'refundTransactions']) ?? $locked;
            }

            if (! $current->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'status' => [
                        "Cannot transition return from [{$current->value}] to [{$next->value}].",
                    ],
                ]);
            }

            // Apply item decisions (including disposition) before Completed validation/restore.
            $this->applyItemDecisions($locked, $input['items'] ?? [], $admin);

            if ($next === ReturnRequestStatus::Completed) {
                $locked->load('items');
                $this->inventoryRestoration->assertItemsReadyForCompletion($locked);
                $this->inventoryRestoration->restoreForCompletedReturn($locked, $admin);
            }

            $locked->status = $next;

            if (array_key_exists('admin_notes', $input)) {
                $locked->admin_notes = $input['admin_notes'];
            }

            if ($next === ReturnRequestStatus::Approved) {
                $locked->approved_by = $admin?->id;
                $locked->approved_at = now();
            }

            if ($next === ReturnRequestStatus::Completed) {
                $locked->completed_at = now();
            }

            $locked->save();

            $fresh = $locked->fresh(['items.orderItem', 'order.user', 'customer', 'approver', 'refundTransactions']) ?? $locked;

            try {
                match ($next) {
                    ReturnRequestStatus::Approved => event(new ReturnApproved($fresh, $admin)),
                    ReturnRequestStatus::Rejected => event(new ReturnRejected($fresh, $admin)),
                    ReturnRequestStatus::Completed => event(new ReturnCompleted($fresh, $admin)),
                    default => null,
                };
            } catch (\Throwable $e) {
                Log::warning('returns.event_status_failed', [
                    'return_id' => $fresh->id,
                    'status' => $next->value,
                    'message' => $e->getMessage(),
                ]);
            }

            return $fresh;
        });
    }

    public function show(ReturnRequest $return): ReturnRequest
    {
        return $return->loadMissing([
            'items.orderItem',
            'order.user',
            'customer',
            'approver',
            'refundTransactions',
        ]);
    }

    public function paginateForCustomer(User $customer, int $perPage = 15): LengthAwarePaginator
    {
        return ReturnRequest::query()
            ->with(['order', 'items'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ReturnRequest::query()
            ->with(['order.user', 'customer', 'items', 'approver', 'latestRefund'])
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        return $query->paginate($perPage);
    }

    /**
     * @param  list<array{
     *     id: string,
     *     condition?: string|null,
     *     resolution?: string|null,
     *     refund_amount?: mixed,
     *     inventory_disposition?: string|null
     * }>  $items
     */
    private function applyItemDecisions(ReturnRequest $return, array $items, ?Admin $admin): void
    {
        if ($items === []) {
            return;
        }

        foreach ($items as $row) {
            /** @var ReturnItem|null $item */
            $item = ReturnItem::query()
                ->where('return_request_id', $return->id)
                ->whereKey($row['id'])
                ->first();

            if ($item === null) {
                continue;
            }

            $oldAmount = $item->refund_amount;

            if (array_key_exists('condition', $row)) {
                $item->condition = $row['condition'];
            }
            if (array_key_exists('resolution', $row) && filled($row['resolution'])) {
                $resolution = ReturnItemResolution::tryFrom((string) $row['resolution']);
                if ($resolution === null) {
                    throw ValidationException::withMessages([
                        'items' => ["Invalid resolution for return item {$item->id}."],
                    ]);
                }
                $item->resolution = $resolution;
            }
            if (array_key_exists('inventory_disposition', $row) && filled($row['inventory_disposition'])) {
                $disposition = InventoryDisposition::tryFrom((string) $row['inventory_disposition']);
                if ($disposition === null) {
                    throw ValidationException::withMessages([
                        'items' => ["Invalid inventory_disposition for return item {$item->id}."],
                    ]);
                }
                $item->inventory_disposition = $disposition;
            }
            if (array_key_exists('refund_amount', $row) && $row['refund_amount'] !== null) {
                $item->refund_amount = $row['refund_amount'];
            }

            $item->save();

            if (
                array_key_exists('refund_amount', $row)
                && $row['refund_amount'] !== null
                && (string) $oldAmount !== (string) $item->refund_amount
            ) {
                // Audit is recorded by ReturnListeners via dedicated audit event from engine side-effects.
                event(\App\Events\Audit\ReturnRefundAmountChanged::fromChange(
                    $return,
                    $item,
                    $oldAmount !== null ? (string) $oldAmount : null,
                    (string) $item->refund_amount,
                    $admin,
                ));
            }
        }
    }
}
