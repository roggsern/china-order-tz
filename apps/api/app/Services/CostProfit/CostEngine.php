<?php

namespace App\Services\CostProfit;

use App\Events\CostProfit\CostUpdated;
use App\Events\CostProfit\OrderCostCaptured;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderCostSnapshot;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Captures immutable line costs at order time. Manual other_cost adjustments allowed once.
 */
class CostEngine
{
    public function __construct(
        private readonly CostSnapshotService $snapshots,
    ) {}

    /**
     * Capture cost snapshots for every order item. Idempotent per order_item_id.
     *
     * @return list<OrderCostSnapshot>
     */
    public function captureForOrder(Order $order, ?Admin $admin = null): array
    {
        return DB::transaction(function () use ($order, $admin) {
            $order->loadMissing(['items.product', 'items.variant']);
            $created = [];

            foreach ($order->items as $item) {
                if (OrderCostSnapshot::query()->where('order_item_id', $item->id)->exists()) {
                    continue;
                }

                $payload = $this->snapshots->resolveForOrderItem($item);
                $snapshot = OrderCostSnapshot::query()->create([
                    'order_item_id' => $item->id,
                    ...$payload,
                    'created_at' => now(),
                ]);
                $created[] = $snapshot;
            }

            if ($created !== []) {
                try {
                    event(new OrderCostCaptured($order->fresh(['items.costSnapshot']) ?? $order, $created, $admin));
                } catch (\Throwable $e) {
                    Log::warning('cost.order_cost_captured_failed', [
                        'order_id' => $order->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            return $created;
        });
    }

    /**
     * Manual adjustment of other_cost on an existing snapshot (audit via CostUpdated).
     * Does not rewrite supplier/shipping snapshot values.
     */
    public function updateOtherCost(OrderCostSnapshot $snapshot, float $otherCost, ?Admin $admin = null): OrderCostSnapshot
    {
        if ($otherCost < 0) {
            throw ValidationException::withMessages([
                'other_cost' => ['Other cost cannot be negative.'],
            ]);
        }

        return DB::transaction(function () use ($snapshot, $otherCost, $admin) {
            /** @var OrderCostSnapshot $locked */
            $locked = OrderCostSnapshot::query()->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['other_cost', 'total_cost']);

            $locked->other_cost = number_format($otherCost, 2, '.', '');
            $locked->total_cost = number_format(
                (float) $locked->supplier_cost + (float) $locked->shipping_cost + $otherCost,
                2,
                '.',
                '',
            );
            $locked->save();

            $fresh = $locked->fresh() ?? $locked;

            try {
                event(new CostUpdated($fresh, $before, $admin));
            } catch (\Throwable $e) {
                Log::warning('cost.cost_updated_failed', [
                    'snapshot_id' => $fresh->id,
                    'message' => $e->getMessage(),
                ]);
            }

            return $fresh;
        });
    }

    public function totalCostForOrder(Order $order): string
    {
        $order->loadMissing('items.costSnapshot');

        $sum = $order->items->sum(function (OrderItem $item) {
            return (float) ($item->costSnapshot?->total_cost ?? 0);
        });

        return number_format($sum, 2, '.', '');
    }
}
