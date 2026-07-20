<?php

namespace App\Services\Orders\Lifecycle;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative Order Lifecycle Engine.
 * Sole production writer for orders.status transitions (except initial create + POS create).
 * Specialist modules keep their own statuses and call into this engine when needed.
 */
class OrderLifecycleEngine
{
    public function recordCreated(Order $order, OrderLifecycleContext $context): void
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::from((string) $order->status);

        $this->writeHistory($order, null, $status, $context);
    }

    public function transition(Order $order, OrderStatus $to, OrderLifecycleContext $context): Order
    {
        return DB::transaction(function () use ($order, $to, $context): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($context->idempotencyKey !== null) {
                $exists = OrderStatusHistory::query()
                    ->where('order_id', $locked->id)
                    ->where('idempotency_key', $context->idempotencyKey)
                    ->exists();
                if ($exists) {
                    return $locked->fresh() ?? $locked;
                }
            }

            $from = $locked->status instanceof OrderStatus
                ? $locked->status
                : OrderStatus::tryFrom((string) $locked->status);

            if ($from === null) {
                throw ValidationException::withMessages([
                    'status' => ['Order has an unknown status and cannot transition safely.'],
                ]);
            }

            if ($from === $to) {
                return $locked->fresh() ?? $locked;
            }

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => [
                        "Cannot transition order from [{$from->value}] to [{$to->value}].",
                    ],
                ]);
            }

            $this->assertBusinessGuards($from, $to, $context);

            $payload = ['status' => $to];
            if ($to === OrderStatus::Paid && $locked->paid_at === null) {
                $payload['paid_at'] = now();
            }
            if (in_array($to, [OrderStatus::Cancelled, OrderStatus::RefundPending], true)
                && $locked->cancelled_at === null
                && $to === OrderStatus::Cancelled
            ) {
                $payload['cancelled_at'] = now();
            }

            $locked->fill($payload)->save();
            $this->writeHistory($locked, $from, $to, $context);

            return $locked->fresh() ?? $locked;
        });
    }

    public function markPaid(Order $order, OrderLifecycleContext $context): Order
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if ($status === OrderStatus::Paid && $order->paid_at !== null) {
            return $order;
        }

        if ($status === null || ! $status->isPayable()) {
            throw ValidationException::withMessages([
                'order' => ['Only pending payment orders can be marked paid.'],
            ]);
        }

        return $this->transition($order, OrderStatus::Paid, $context);
    }

    public function syncFromFulfillment(Order $order, FulfillmentStatus $fulfillmentStatus): Order
    {
        $target = match ($fulfillmentStatus) {
            FulfillmentStatus::Processing, FulfillmentStatus::ReadyForShipping => OrderStatus::Processing,
            FulfillmentStatus::Shipped => OrderStatus::Shipped,
            FulfillmentStatus::Delivered => OrderStatus::Delivered,
            default => null,
        };

        if ($target === null) {
            return $order;
        }

        return DB::transaction(function () use ($order, $target, $fulfillmentStatus): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $current = $locked->status instanceof OrderStatus
                ? $locked->status
                : OrderStatus::tryFrom((string) $locked->status);

            if ($current === null || $current->isTerminal() || $current === $target) {
                return $locked;
            }

            $ctx = OrderLifecycleContext::fulfillment(
                "Synced from fulfillment [{$fulfillmentStatus->value}]",
                ['fulfillment_status' => $fulfillmentStatus->value],
            );

            // Step Paid/Confirmed → Processing before Shipped/Delivered when needed.
            if (in_array($current, [OrderStatus::Paid, OrderStatus::Confirmed], true)
                && in_array($target, [OrderStatus::Shipped, OrderStatus::Delivered], true)
            ) {
                if ($current->canTransitionTo(OrderStatus::Processing)) {
                    $locked = $this->transition($locked, OrderStatus::Processing, $ctx);
                    $current = OrderStatus::Processing;
                }
            }

            if ($current === $target) {
                return $locked->fresh() ?? $locked;
            }

            if (! $current->canTransitionTo($target)) {
                return $locked->fresh() ?? $locked;
            }

            return $this->transition($locked, $target, $ctx);
        });
    }

    public function customerMayCancel(Order $order): bool
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if ($status === null || $status->isTerminal() || $status === OrderStatus::RefundPending) {
            return false;
        }

        if ($status->isPrePayment()) {
            return true;
        }

        if (! in_array($status, [OrderStatus::Paid, OrderStatus::Confirmed, OrderStatus::Processing], true)) {
            return false;
        }

        $order->loadMissing('fulfillment');
        if ($order->fulfillment === null) {
            return true;
        }

        $fs = $order->fulfillment->status instanceof FulfillmentStatus
            ? $order->fulfillment->status
            : FulfillmentStatus::tryFrom((string) $order->fulfillment->status);

        return $fs === null
            || in_array($fs, [FulfillmentStatus::Pending, FulfillmentStatus::Processing], true);
    }

    public function cancel(Order $order, OrderLifecycleContext $context): Order
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if ($status === OrderStatus::Cancelled) {
            return $order;
        }

        if ($status === OrderStatus::RefundPending) {
            return $order;
        }

        if ($status === null || $status->isTerminal()) {
            throw ValidationException::withMessages([
                'order' => ['This order cannot be cancelled.'],
            ]);
        }

        if ($context->user !== null && ! $this->customerMayCancel($order)) {
            throw ValidationException::withMessages([
                'order' => ['Cancellation is no longer available. Use the return/refund process if eligible.'],
            ]);
        }

        if (in_array($status, [OrderStatus::Shipped, OrderStatus::Delivered], true)) {
            throw ValidationException::withMessages([
                'order' => ['Shipped or delivered orders cannot be cancelled. Request a return instead.'],
            ]);
        }

        $order->loadMissing('fulfillment');
        if ($order->fulfillment !== null) {
            $fs = $order->fulfillment->status instanceof FulfillmentStatus
                ? $order->fulfillment->status
                : FulfillmentStatus::tryFrom((string) $order->fulfillment->status);
            if (in_array($fs, [
                FulfillmentStatus::ReadyForShipping,
                FulfillmentStatus::Shipped,
                FulfillmentStatus::Delivered,
            ], true)) {
                throw ValidationException::withMessages([
                    'order' => ['Cancellation cutoff passed: fulfillment is already advanced. Use return/refund.'],
                ]);
            }
        }

        if (in_array($status, [OrderStatus::Paid, OrderStatus::Confirmed, OrderStatus::Processing], true)
            && $order->paid_at !== null
        ) {
            return $this->transition(
                $order,
                OrderStatus::RefundPending,
                new OrderLifecycleContext(
                    source: $context->source,
                    reason: ($context->reason ?? 'Cancellation after payment').' — refund required',
                    admin: $context->admin,
                    user: $context->user,
                    idempotencyKey: $context->idempotencyKey,
                    metadata: array_merge($context->metadata, [
                        'payment_consequence' => 'refund_pending',
                    ]),
                ),
            );
        }

        return $this->transition($order, OrderStatus::Cancelled, $context);
    }

    public function markRefunded(Order $order, OrderLifecycleContext $context): Order
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if ($status === OrderStatus::Refunded) {
            return $order;
        }

        if ($status === OrderStatus::RefundPending) {
            return $this->transition($order, OrderStatus::Refunded, $context);
        }

        // Delivered/completed return refund: step through refund_pending then refunded.
        if (in_array($status, [OrderStatus::Delivered, OrderStatus::Completed, OrderStatus::Cancelled], true)) {
            if ($status->canTransitionTo(OrderStatus::RefundPending)) {
                $order = $this->transition($order, OrderStatus::RefundPending, $context);
            }
            $fresh = $order->fresh() ?? $order;
            $now = $fresh->status instanceof OrderStatus
                ? $fresh->status
                : OrderStatus::tryFrom((string) $fresh->status);
            if ($now === OrderStatus::RefundPending) {
                return $this->transition($fresh, OrderStatus::Refunded, $context);
            }
        }

        throw ValidationException::withMessages([
            'order' => ['Order cannot be marked refunded from its current state.'],
        ]);
    }

    private function assertBusinessGuards(
        OrderStatus $from,
        OrderStatus $to,
        OrderLifecycleContext $context,
    ): void {
        if ($to === OrderStatus::Paid
            && ! in_array($context->source, ['payment', 'admin_pay', 'pos'], true)
        ) {
            throw ValidationException::withMessages([
                'status' => ['Paid state may only be set via verified payment completion or authorized admin pay.'],
            ]);
        }

        if (in_array($to, [OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Completed], true)
            && $from->isPrePayment()
        ) {
            throw ValidationException::withMessages([
                'status' => ['Unpaid orders cannot enter fulfillment or delivery states.'],
            ]);
        }

        if ($to === OrderStatus::Refunded && $context->source !== 'refund') {
            throw ValidationException::withMessages([
                'status' => ['Refunded state requires verified refund completion.'],
            ]);
        }
    }

    private function writeHistory(
        Order $order,
        ?OrderStatus $from,
        OrderStatus $to,
        OrderLifecycleContext $context,
    ): void {
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'changed_by_admin_id' => $context->admin?->id,
            'changed_by_user_id' => $context->user?->id,
            'previous_status' => $from?->value,
            'new_status' => $to->value,
            'notes' => $context->reason,
            'source' => $context->source,
            'actor_type' => $context->admin ? 'admin' : ($context->user ? 'customer' : 'system'),
            'metadata' => $context->metadata !== [] ? $context->metadata : null,
            'idempotency_key' => $context->idempotencyKey,
        ]);
    }
}
