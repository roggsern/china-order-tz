<?php

namespace App\Services\Fulfillment;

use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\OrderStatus;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Services\Fulfillment\Contracts\FulfillmentStrategyInterface;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fulfillment Engine — starts after order is paid.
 * Creates a fulfillment record and stops (no shipments/labels/notifications).
 *
 * LOCKED MODULE NOTE (Lifecycle Closure #2): status updates sync top-level order via OrderLifecycleEngine.
 */
class FulfillmentEngine
{
    /** @var array<string, FulfillmentStrategyInterface> */
    private array $strategies = [];

    /**
     * @param  iterable<FulfillmentStrategyInterface>  $strategies
     */
    public function __construct(
        iterable $strategies,
        private readonly OrderLifecycleEngine $lifecycle,
    ) {
        foreach ($strategies as $strategy) {
            $this->strategies[$strategy->key()->value] = $strategy;
        }
    }

    /**
     * Create fulfillment for a paid order (idempotent).
     */
    public function createForOrder(Order $order, ?string $notes = null, ?string $assignedTo = null): Fulfillment
    {
        $order->loadMissing(['items.product.supplier', 'items.shippingMethodRecord', 'fulfillment']);

        if ($order->status !== OrderStatus::Paid) {
            throw ValidationException::withMessages([
                'order' => ['Fulfillment can only be created for paid orders.'],
            ]);
        }

        if ($order->fulfillment !== null) {
            return $order->fulfillment->load(['order.user', 'assignee']);
        }

        $strategy = $this->resolveStrategy($order);

        return DB::transaction(function () use ($order, $strategy, $notes, $assignedTo): Fulfillment {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $existing = Fulfillment::query()->where('order_id', $locked->id)->first();
            if ($existing !== null) {
                return $existing->load(['order.user', 'assignee']);
            }

            $fulfillment = Fulfillment::query()->create([
                'order_id' => $locked->id,
                'strategy' => $strategy->key(),
                'status' => FulfillmentStatus::Pending,
                'assigned_to' => $assignedTo,
                'notes' => $notes,
            ]);

            $strategy->bootstrap($fulfillment);

            app(\App\Services\Warehouse\WarehouseEngine::class)
                ->createForFulfillment($fulfillment);

            return $fulfillment->fresh(['order.user', 'assignee', 'warehouseJob']) ?? $fulfillment;
        });
    }

    public function resolveStrategy(Order $order): FulfillmentStrategyInterface
    {
        $china = $this->strategies[FulfillmentStrategy::China->value] ?? null;
        if ($china !== null && $china->appliesTo($order)) {
            return $china;
        }

        $local = $this->strategies[FulfillmentStrategy::Local->value] ?? null;
        if ($local !== null) {
            return $local;
        }

        throw ValidationException::withMessages([
            'strategy' => ['No fulfillment strategy is registered.'],
        ]);
    }

    public function show(Fulfillment $fulfillment): Fulfillment
    {
        return $fulfillment->loadMissing(['order.user', 'assignee']);
    }

    /**
     * @param  array{status?: string, assigned_to?: string|null, notes?: string|null}  $input
     */
    public function updateStatus(Fulfillment $fulfillment, array $input): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment, $input): Fulfillment {
            /** @var Fulfillment $locked */
            $locked = Fulfillment::query()->whereKey($fulfillment->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('status', $input) && $input['status'] !== null) {
                $next = FulfillmentStatus::from((string) $input['status']);
                $current = $locked->status instanceof FulfillmentStatus
                    ? $locked->status
                    : FulfillmentStatus::from((string) $locked->status);

                if ($current === $next) {
                    // Idempotent no-op for same status.
                } elseif (! $current->canTransitionTo($next)) {
                    throw ValidationException::withMessages([
                        'status' => ["Cannot transition fulfillment from [{$current->value}] to [{$next->value}]."],
                    ]);
                } else {
                    $locked->status = $next;

                    if ($next === FulfillmentStatus::Processing && $locked->started_at === null) {
                        $locked->started_at = now();
                    }

                    if ($next === FulfillmentStatus::Delivered) {
                        $locked->completed_at = $locked->completed_at ?? now();
                    }

                    if ($next === FulfillmentStatus::Cancelled) {
                        $locked->completed_at = $locked->completed_at ?? now();
                    }
                }
            }

            if (array_key_exists('assigned_to', $input)) {
                $locked->assigned_to = $input['assigned_to'];
            }

            if (array_key_exists('notes', $input) && $input['notes'] !== null) {
                $locked->notes = $input['notes'];
            }

            $locked->save();

            $fresh = $locked->fresh(['order.user', 'assignee']) ?? $locked;

            if (array_key_exists('status', $input) && $input['status'] !== null && $fresh->order !== null) {
                $next = $fresh->status instanceof FulfillmentStatus
                    ? $fresh->status
                    : FulfillmentStatus::tryFrom((string) $fresh->status);
                if ($next !== null) {
                    $this->lifecycle->syncFromFulfillment($fresh->order, $next);
                }
            }

            return $fresh->fresh(['order.user', 'assignee']) ?? $fresh;
        });
    }

    /**
     * @return list<string>
     */
    public function registeredStrategies(): array
    {
        return array_keys($this->strategies);
    }
}
