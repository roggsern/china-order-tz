<?php

namespace App\Services\CostProfit;

use App\Enums\OrderStatus;
use App\Events\CostProfit\ProfitCalculated;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderCostSnapshot;
use App\Models\OrderItem;
use App\Models\ProfitRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Calculates order profit from immutable cost snapshots + order revenue.
 * Does not recompute historical exchange rates or live supplier prices.
 */
class ProfitEngine
{
    public function __construct(
        private readonly CostEngine $costs,
    ) {}

    /**
     * Calculate (or refresh from existing snapshots) profit for a completed/paid order.
     */
    public function calculateForOrder(Order $order, ?Admin $admin = null, bool $force = false): ProfitRecord
    {
        return DB::transaction(function () use ($order, $admin, $force) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $this->isEligible($locked) && ! $force) {
                throw ValidationException::withMessages([
                    'order' => ['Profit is calculated after the order is paid (or completed).'],
                ]);
            }

            // Ensure snapshots exist (orders created before engine still get costs once).
            $this->costs->captureForOrder($locked);

            $locked->load(['items.costSnapshot']);

            $revenue = (float) ($locked->total ?? 0);
            $totalCost = (float) $this->costs->totalCostForOrder($locked);
            $gross = round($revenue - $totalCost, 2);
            $margin = $revenue > 0 ? round(($gross / $revenue) * 100, 4) : 0.0;
            $currency = strtoupper((string) ($locked->currency ?: 'TZS'));

            $record = ProfitRecord::query()->updateOrCreate(
                ['order_id' => $locked->id],
                [
                    'revenue' => number_format($revenue, 2, '.', ''),
                    'total_cost' => number_format($totalCost, 2, '.', ''),
                    'gross_profit' => number_format($gross, 2, '.', ''),
                    'margin_percentage' => number_format($margin, 4, '.', ''),
                    'currency' => $currency,
                    'calculated_at' => now(),
                ],
            );

            try {
                event(new ProfitCalculated($record->fresh(['order']) ?? $record, $admin));
            } catch (\Throwable $e) {
                Log::warning('profit.calculated_event_failed', [
                    'order_id' => $locked->id,
                    'message' => $e->getMessage(),
                ]);
            }

            $this->maybeAlertLowMargin($record);

            return $record->fresh(['order']) ?? $record;
        });
    }

    /**
     * Reverse revenue/profit for a completed return without duplicating margin math.
     * Reduces recorded revenue by refund amount and scales cost proportionally.
     */
    public function reverseForReturn(Order $order, string $refundAmount, ?Admin $admin = null): ?ProfitRecord
    {
        if (bccomp($refundAmount, '0.00', 2) <= 0) {
            return ProfitRecord::query()->where('order_id', $order->id)->first();
        }

        return DB::transaction(function () use ($order, $refundAmount, $admin) {
            $record = ProfitRecord::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if ($record === null) {
                try {
                    $record = $this->calculateForOrder($order, $admin, force: true);
                } catch (\Throwable) {
                    return null;
                }
                $record = ProfitRecord::query()->whereKey($record->id)->lockForUpdate()->first();
            }

            if ($record === null) {
                return null;
            }

            $revenue = number_format((float) $record->revenue, 2, '.', '');
            $cost = number_format((float) $record->total_cost, 2, '.', '');
            $refund = number_format((float) $refundAmount, 2, '.', '');

            if (bccomp($revenue, '0.00', 2) <= 0) {
                return $record;
            }

            $ratio = bcdiv($refund, $revenue, 6);
            $costReversal = bcmul($cost, $ratio, 2);
            $newRevenue = bcsub($revenue, $refund, 2);
            if (bccomp($newRevenue, '0.00', 2) < 0) {
                $newRevenue = '0.00';
            }
            $newCost = bcsub($cost, $costReversal, 2);
            if (bccomp($newCost, '0.00', 2) < 0) {
                $newCost = '0.00';
            }
            $gross = bcsub($newRevenue, $newCost, 2);
            $margin = bccomp($newRevenue, '0.00', 2) > 0
                ? bcmul(bcdiv($gross, $newRevenue, 6), '100', 4)
                : '0.0000';

            $record->forceFill([
                'revenue' => $newRevenue,
                'total_cost' => $newCost,
                'gross_profit' => $gross,
                'margin_percentage' => $margin,
                'calculated_at' => now(),
            ])->save();

            try {
                event(new ProfitCalculated($record->fresh(['order']) ?? $record, $admin));
            } catch (\Throwable $e) {
                Log::warning('profit.return_reversal_event_failed', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }

            return $record->fresh(['order']);
        });
    }

    public function isEligible(Order $order): bool
    {
        return in_array($order->status, [
            OrderStatus::Paid,
            OrderStatus::Confirmed,
            OrderStatus::Processing,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
            OrderStatus::Completed,
        ], true);
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return array{
     *     revenue: string,
     *     total_cost: string,
     *     gross_profit: string,
     *     margin_percentage: string,
     *     orders_count: int,
     *     currency: string
     * }
     */
    public function dashboard(array $filters = []): array
    {
        $query = ProfitRecord::query()->whereHas('order', fn ($q) => $q->real());
        $this->applyDateFilter($query, $filters);

        $agg = (clone $query)->selectRaw('
            COALESCE(SUM(revenue), 0) as revenue,
            COALESCE(SUM(total_cost), 0) as total_cost,
            COALESCE(SUM(gross_profit), 0) as gross_profit,
            COUNT(*) as orders_count
        ')->first();

        $revenue = (float) ($agg->revenue ?? 0);
        $cost = (float) ($agg->total_cost ?? 0);
        $profit = (float) ($agg->gross_profit ?? 0);
        $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 4) : 0.0;

        return [
            'revenue' => number_format($revenue, 2, '.', ''),
            'total_cost' => number_format($cost, 2, '.', ''),
            'gross_profit' => number_format($profit, 2, '.', ''),
            'margin_percentage' => number_format($margin, 4, '.', ''),
            'orders_count' => (int) ($agg->orders_count ?? 0),
            'currency' => 'TZS',
        ];
    }

    public function paginateOrders(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ProfitRecord::query()
            ->with(['order:id,order_number,status,commerce_channel_id,commerce_channel_snapshot,placed_at,total,currency'])
            ->whereHas('order', fn ($q) => $q->real())
            ->latest('calculated_at');

        $this->applyDateFilter($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byProducts(array $filters = [], int $limit = 20): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $rows = OrderItem::query()
            ->selectRaw('
                order_items.product_id,
                order_items.product_variant_id,
                MAX(order_items.product_name_snapshot) as product_name,
                MAX(order_items.variant_name_snapshot) as variant_name,
                MAX(order_items.sku_snapshot) as sku,
                SUM(order_items.line_total) as revenue,
                SUM(COALESCE(order_cost_snapshots.total_cost, 0)) as total_cost,
                SUM(order_items.line_total) - SUM(COALESCE(order_cost_snapshots.total_cost, 0)) as gross_profit,
                SUM(order_items.quantity) as units
            ')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('profit_records', 'profit_records.order_id', '=', 'orders.id')
            ->leftJoin('order_cost_snapshots', 'order_cost_snapshots.order_item_id', '=', 'order_items.id')
            ->whereNull('orders.deleted_at')
            ->where('orders.is_demo', false)
            ->when($from, fn ($q) => $q->whereDate('profit_records.calculated_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('profit_records.calculated_at', '<=', $to))
            ->groupBy('order_items.product_id', 'order_items.product_variant_id')
            ->orderByDesc('gross_profit')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            $revenue = (float) $row->revenue;
            $profit = (float) $row->gross_profit;
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 4) : 0.0;

            return [
                'product_id' => $row->product_id,
                'product_variant_id' => $row->product_variant_id,
                'product_name' => $row->product_name,
                'variant_name' => $row->variant_name,
                'sku' => $row->sku,
                'units' => (int) $row->units,
                'revenue' => number_format($revenue, 2, '.', ''),
                'total_cost' => number_format((float) $row->total_cost, 2, '.', ''),
                'gross_profit' => number_format($profit, 2, '.', ''),
                'margin_percentage' => number_format($margin, 4, '.', ''),
            ];
        })->all();
    }

    /**
     * Low-margin products (margin below threshold).
     *
     * @return list<array<string, mixed>>
     */
    public function lowMarginProducts(array $filters = [], float $threshold = 15.0, int $limit = 20): array
    {
        $products = $this->byProducts($filters, 200);

        $filtered = array_values(array_filter(
            $products,
            fn (array $row) => (float) $row['margin_percentage'] < $threshold
                && (float) $row['revenue'] > 0,
        ));

        usort($filtered, fn ($a, $b) => (float) $a['margin_percentage'] <=> (float) $b['margin_percentage']);

        return array_slice($filtered, 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bySuppliers(array $filters = [], int $limit = 20): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $rows = OrderItem::query()
            ->selectRaw('
                products.supplier_id,
                MAX(suppliers.name) as supplier_name,
                MAX(suppliers.code) as supplier_code,
                SUM(order_items.line_total) as revenue,
                SUM(COALESCE(order_cost_snapshots.total_cost, 0)) as total_cost,
                SUM(order_items.line_total) - SUM(COALESCE(order_cost_snapshots.total_cost, 0)) as gross_profit
            ')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('profit_records', 'profit_records.order_id', '=', 'orders.id')
            ->leftJoin('order_cost_snapshots', 'order_cost_snapshots.order_item_id', '=', 'order_items.id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'products.supplier_id')
            ->whereNull('orders.deleted_at')
            ->where('orders.is_demo', false)
            ->whereNotNull('products.supplier_id')
            ->when($from, fn ($q) => $q->whereDate('profit_records.calculated_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('profit_records.calculated_at', '<=', $to))
            ->groupBy('products.supplier_id')
            ->orderByDesc('gross_profit')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            $revenue = (float) $row->revenue;
            $profit = (float) $row->gross_profit;
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 4) : 0.0;

            return [
                'supplier_id' => $row->supplier_id,
                'supplier_name' => $row->supplier_name,
                'supplier_code' => $row->supplier_code,
                'revenue' => number_format($revenue, 2, '.', ''),
                'total_cost' => number_format((float) $row->total_cost, 2, '.', ''),
                'gross_profit' => number_format($profit, 2, '.', ''),
                'margin_percentage' => number_format($margin, 4, '.', ''),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byCommerceChannel(array $filters = []): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $records = ProfitRecord::query()
            ->with('order:id,commerce_channel_snapshot,commerce_channel_id')
            ->whereHas('order', fn ($q) => $q->real())
            ->when($from, fn ($q) => $q->whereDate('calculated_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('calculated_at', '<=', $to))
            ->get();

        /** @var Collection<string, array{code: string, name: string, revenue: float, total_cost: float, gross_profit: float}> $grouped */
        $grouped = collect();

        foreach ($records as $record) {
            $snap = $record->order?->commerce_channel_snapshot ?? [];
            $code = (string) ($snap['code'] ?? 'UNKNOWN');
            $name = (string) ($snap['name'] ?? $code);
            $bucket = $grouped->get($code, [
                'code' => $code,
                'name' => $name,
                'revenue' => 0.0,
                'total_cost' => 0.0,
                'gross_profit' => 0.0,
            ]);
            $bucket['revenue'] += (float) $record->revenue;
            $bucket['total_cost'] += (float) $record->total_cost;
            $bucket['gross_profit'] += (float) $record->gross_profit;
            $grouped->put($code, $bucket);
        }

        return $grouped->values()->map(function (array $row) {
            $margin = $row['revenue'] > 0
                ? round(($row['gross_profit'] / $row['revenue']) * 100, 4)
                : 0.0;

            return [
                'commerce_channel_code' => $row['code'],
                'commerce_channel_name' => $row['name'],
                'revenue' => number_format($row['revenue'], 2, '.', ''),
                'total_cost' => number_format($row['total_cost'], 2, '.', ''),
                'gross_profit' => number_format($row['gross_profit'], 2, '.', ''),
                'margin_percentage' => number_format($margin, 4, '.', ''),
            ];
        })->all();
    }

    private function applyDateFilter($query, array $filters): void
    {
        if (filled($filters['from'] ?? null)) {
            $query->whereDate('calculated_at', '>=', $filters['from']);
        }
        if (filled($filters['to'] ?? null)) {
            $query->whereDate('calculated_at', '<=', $filters['to']);
        }
    }

    private function maybeAlertLowMargin(ProfitRecord $record): void
    {
        $threshold = (float) config('cost_profit.low_margin_threshold', 10);
        if ((float) $record->margin_percentage >= $threshold) {
            return;
        }

        try {
            event(new \App\Events\CostProfit\LowMarginDetected($record, $threshold));
        } catch (\Throwable $e) {
            Log::warning('profit.low_margin_event_failed', [
                'order_id' => $record->order_id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
