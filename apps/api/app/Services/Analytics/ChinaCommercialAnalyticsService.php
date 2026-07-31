<?php

namespace App\Services\Analytics;

use App\Enums\CommerceChannelCode;
use App\Models\CommerceChannel;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read-only China import commercial analytics over immutable cost snapshots + profit records.
 * Does not mutate pricing, inventory, orders, or suppliers.
 */
class ChinaCommercialAnalyticsService
{
    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function overview(array $filters = []): array
    {
        $agg = $this->chinaLineAggregates($filters);
        $catalog = $this->catalogMetrics();

        $revenue = (float) $agg['revenue'];
        $landed = (float) $agg['total_landed_cost'];
        $profit = (float) $agg['gross_profit'];
        $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 4) : 0.0;

        return [
            'currency' => 'TZS',
            'period' => $this->periodMeta($filters),
            'total_china_products' => $catalog['total_china_products'],
            'total_imported_quantity' => $catalog['total_imported_quantity'],
            'total_import_value' => $this->money($agg['supplier_cost']),
            'total_landed_cost' => $this->money($landed),
            'total_sales_generated' => $this->money($revenue),
            'gross_profit' => $this->money($profit),
            'gross_margin_percentage' => $this->percent($margin),
            'units_sold' => (int) $agg['units_sold'],
            'orders_count' => (int) $agg['orders_count'],
            'volume_trend' => $this->volumeTrend($filters),
            'revenue_vs_cost' => [
                'revenue' => $this->money($revenue),
                'cost' => $this->money($landed),
                'profit' => $this->money($profit),
            ],
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function landedCost(array $filters = []): array
    {
        $agg = $this->chinaLineAggregates($filters);
        $units = max(1, (int) $agg['units_sold']);
        $totalLanded = (float) $agg['total_landed_cost'];

        return [
            'currency' => 'TZS',
            'period' => $this->periodMeta($filters),
            'average_landed_cost_per_unit' => $this->money($totalLanded / $units),
            'components' => [
                'supplier_cost' => $this->money($agg['supplier_cost']),
                'china_logistics_and_freight' => $this->money($agg['shipping_cost']),
                'warehouse_china_costs' => $this->money(0),
                'other_import_costs' => $this->money($agg['other_cost']),
                'total_landed_cost' => $this->money($totalLanded),
            ],
            'by_product' => $this->landedCostByProduct($filters),
            'by_category' => $this->landedCostByCategory($filters),
            'by_supplier' => $this->landedCostBySupplier($filters),
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null, limit?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function suppliers(array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 25), 100));
        $rows = $this->chinaOrderItemsQuery($filters)
            ->selectRaw('
                products.supplier_id,
                MAX(suppliers.name) as supplier_name,
                MAX(suppliers.code) as supplier_code,
                COUNT(DISTINCT order_items.product_id) as products_count,
                SUM(order_items.quantity) as quantity_sold,
                SUM(COALESCE(purchase_received.quantity_received, 0)) as quantity_received,
                SUM(order_items.line_total) as revenue,
                SUM(COALESCE(order_cost_snapshots.total_cost, 0)) as total_cost,
                SUM(order_items.line_total) - SUM(COALESCE(order_cost_snapshots.total_cost, 0)) as gross_profit
            ')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'products.supplier_id')
            ->leftJoinSub(
                PurchaseOrderItem::query()
                    ->selectRaw('product_variant_id, SUM(quantity_received) as quantity_received')
                    ->groupBy('product_variant_id'),
                'purchase_received',
                'purchase_received.product_variant_id',
                '=',
                'order_items.product_variant_id',
            )
            ->whereNotNull('products.supplier_id')
            ->groupBy('products.supplier_id')
            ->orderByDesc('gross_profit')
            ->limit($limit)
            ->get();

        $ranking = $rows->values()->map(function ($row, int $index) {
            $revenue = (float) $row->revenue;
            $profit = (float) $row->gross_profit;
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 4) : 0.0;

            return [
                'rank' => $index + 1,
                'supplier_id' => $row->supplier_id,
                'supplier_name' => $row->supplier_name,
                'supplier_code' => $row->supplier_code,
                'products_supplied' => (int) $row->products_count,
                'quantity_received' => (int) $row->quantity_received,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => $this->money($revenue),
                'total_cost' => $this->money((float) $row->total_cost),
                'gross_profit' => $this->money($profit),
                'margin_percentage' => $this->percent($margin),
            ];
        })->all();

        return [
            'currency' => 'TZS',
            'period' => $this->periodMeta($filters),
            'ranking' => $ranking,
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null, limit?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function categories(array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 25), 100));

        $rows = $this->chinaOrderItemsQuery($filters)
            ->selectRaw('
                products.category_id,
                MAX(categories.name) as category_name,
                SUM(order_items.quantity) as imported_units,
                SUM(order_items.line_total) as revenue,
                SUM(COALESCE(order_cost_snapshots.total_cost, 0)) as total_cost,
                SUM(order_items.line_total) - SUM(COALESCE(order_cost_snapshots.total_cost, 0)) as gross_profit
            ')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy('products.category_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        $categories = $rows->map(function ($row) {
            $revenue = (float) $row->revenue;
            $profit = (float) $row->gross_profit;
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 4) : 0.0;

            return [
                'category_id' => $row->category_id,
                'category_name' => $row->category_name ?? 'Uncategorized',
                'imported_units' => (int) $row->imported_units,
                'revenue' => $this->money($revenue),
                'total_cost' => $this->money((float) $row->total_cost),
                'gross_profit' => $this->money($profit),
                'margin_percentage' => $this->percent($margin),
            ];
        })->all();

        return [
            'currency' => 'TZS',
            'period' => $this->periodMeta($filters),
            'categories' => $categories,
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function shipments(array $filters = []): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $chinaCode = CommerceChannelCode::ChinaImport->value;

        $shipmentsQuery = Shipment::query()
            ->whereHas('order', function (Builder $q) use ($chinaCode) {
                $q->real()->where(function (Builder $inner) use ($chinaCode) {
                    $inner->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(commerce_channel_snapshot, '$.code')) = ?", [$chinaCode])
                        ->orWhereHas('items.product.commerceChannel', fn (Builder $c) => $c->where('code', $chinaCode))
                        ->orWhereHas('items.product', fn (Builder $p) => $p->where('fulfillment_source', CommerceChannelCode::ChinaImport->fulfillmentSource()));
                });
            })
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));

        $count = (clone $shipmentsQuery)->count();

        $transitRows = (clone $shipmentsQuery)
            ->whereNotNull('shipped_at')
            ->whereNotNull('delivered_at')
            ->get(['shipped_at', 'delivered_at']);

        $avgTransitDays = $transitRows->isEmpty()
            ? null
            : round($transitRows->avg(fn (Shipment $s) => $s->shipped_at->diffInDays($s->delivered_at)), 1);

        $freightAgg = $this->chinaLineAggregates($filters);

        $units = max(1, (int) $freightAgg['units_sold']);
        $freightTotal = (float) $freightAgg['shipping_cost'];

        return [
            'currency' => 'TZS',
            'period' => $this->periodMeta($filters),
            'shipments_count' => $count,
            'average_shipment_cost' => $count > 0 ? $this->money($freightTotal / $count) : $this->money(0),
            'total_freight_cost' => $this->money($freightTotal),
            'average_transit_days' => $avgTransitDays,
            'cost_per_unit' => $this->money($freightTotal / $units),
            'margin_by_supplier' => collect($this->suppliers(array_merge($filters, ['limit' => 10]))['ranking'] ?? [])
                ->map(fn (array $row) => [
                    'label' => $row['supplier_name'],
                    'margin_percentage' => $row['margin_percentage'],
                ])->all(),
            'margin_by_category' => collect($this->categories(array_merge($filters, ['limit' => 10]))['categories'] ?? [])
                ->map(fn (array $row) => [
                    'label' => $row['category_name'],
                    'margin_percentage' => $row['margin_percentage'],
                ])->all(),
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return array{
     *     revenue: float,
     *     supplier_cost: float,
     *     shipping_cost: float,
     *     other_cost: float,
     *     total_landed_cost: float,
     *     gross_profit: float,
     *     units_sold: int,
     *     orders_count: int
     * }
     */
    private function chinaLineAggregates(array $filters): array
    {
        $row = $this->chinaOrderItemsQuery($filters)
            ->selectRaw('
                COUNT(DISTINCT orders.id) as orders_count,
                SUM(order_items.quantity) as units_sold,
                SUM(order_items.line_total) as revenue,
                SUM(COALESCE(order_cost_snapshots.supplier_cost, 0)) as supplier_cost,
                SUM(COALESCE(order_cost_snapshots.shipping_cost, 0)) as shipping_cost,
                SUM(COALESCE(order_cost_snapshots.other_cost, 0)) as other_cost,
                SUM(COALESCE(order_cost_snapshots.total_cost, 0)) as total_landed_cost,
                SUM(order_items.line_total) - SUM(COALESCE(order_cost_snapshots.total_cost, 0)) as gross_profit
            ')
            ->first();

        return [
            'orders_count' => (int) ($row->orders_count ?? 0),
            'units_sold' => (int) ($row->units_sold ?? 0),
            'revenue' => (float) ($row->revenue ?? 0),
            'supplier_cost' => (float) ($row->supplier_cost ?? 0),
            'shipping_cost' => (float) ($row->shipping_cost ?? 0),
            'other_cost' => (float) ($row->other_cost ?? 0),
            'total_landed_cost' => (float) ($row->total_landed_cost ?? 0),
            'gross_profit' => (float) ($row->gross_profit ?? 0),
        ];
    }

    /**
     * @return array{total_china_products: int, total_imported_quantity: int}
     */
    private function catalogMetrics(): array
    {
        $chinaChannelId = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->value('id');
        $legacySource = CommerceChannelCode::ChinaImport->fulfillmentSource();

        $productsQuery = Product::query()->whereNull('deleted_at');

        if ($chinaChannelId) {
            $productsQuery->where(function (Builder $q) use ($chinaChannelId, $legacySource) {
                $q->where('commerce_channel_id', $chinaChannelId)
                    ->orWhere('fulfillment_source', $legacySource);
            });
        } else {
            $productsQuery->where('fulfillment_source', $legacySource);
        }

        $importedQty = (int) PurchaseOrderItem::query()
            ->whereHas('variant.product', function (Builder $q) use ($chinaChannelId, $legacySource) {
                if ($chinaChannelId) {
                    $q->where(function (Builder $inner) use ($chinaChannelId, $legacySource) {
                        $inner->where('commerce_channel_id', $chinaChannelId)
                            ->orWhere('fulfillment_source', $legacySource);
                    });
                } else {
                    $q->where('fulfillment_source', $legacySource);
                }
            })
            ->sum('quantity_received');

        return [
            'total_china_products' => (int) $productsQuery->count(),
            'total_imported_quantity' => $importedQty,
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    private function landedCostByProduct(array $filters, int $limit = 20): array
    {
        return $this->chinaOrderItemsQuery($filters)
            ->selectRaw('
                order_items.product_id,
                MAX(order_items.product_name_snapshot) as product_name,
                SUM(order_items.quantity) as units,
                SUM(COALESCE(order_cost_snapshots.total_cost, 0)) as total_landed_cost
            ')
            ->groupBy('order_items.product_id')
            ->orderByDesc('total_landed_cost')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $units = max(1, (int) $row->units);
                $landed = (float) $row->total_landed_cost;

                return [
                    'product_id' => $row->product_id,
                    'product_name' => $row->product_name,
                    'units' => (int) $row->units,
                    'total_landed_cost' => $this->money($landed),
                    'average_landed_cost' => $this->money($landed / $units),
                ];
            })
            ->all();
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    private function landedCostByCategory(array $filters, int $limit = 20): array
    {
        return $this->chinaOrderItemsQuery($filters)
            ->selectRaw('
                products.category_id,
                MAX(categories.name) as category_name,
                SUM(order_items.quantity) as units,
                SUM(COALESCE(order_cost_snapshots.total_cost, 0)) as total_landed_cost
            ')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy('products.category_id')
            ->orderByDesc('total_landed_cost')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $units = max(1, (int) $row->units);
                $landed = (float) $row->total_landed_cost;

                return [
                    'category_id' => $row->category_id,
                    'category_name' => $row->category_name ?? 'Uncategorized',
                    'units' => (int) $row->units,
                    'total_landed_cost' => $this->money($landed),
                    'average_landed_cost' => $this->money($landed / $units),
                ];
            })
            ->all();
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    private function landedCostBySupplier(array $filters, int $limit = 20): array
    {
        return $this->chinaOrderItemsQuery($filters)
            ->selectRaw('
                products.supplier_id,
                MAX(suppliers.name) as supplier_name,
                SUM(order_items.quantity) as units,
                SUM(COALESCE(order_cost_snapshots.total_cost, 0)) as total_landed_cost
            ')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'products.supplier_id')
            ->whereNotNull('products.supplier_id')
            ->groupBy('products.supplier_id')
            ->orderByDesc('total_landed_cost')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $units = max(1, (int) $row->units);
                $landed = (float) $row->total_landed_cost;

                return [
                    'supplier_id' => $row->supplier_id,
                    'supplier_name' => $row->supplier_name,
                    'units' => (int) $row->units,
                    'total_landed_cost' => $this->money($landed),
                    'average_landed_cost' => $this->money($landed / $units),
                ];
            })
            ->all();
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return list<array{period: string, units: int, revenue: string, landed_cost: string}>
     */
    private function volumeTrend(array $filters): array
    {
        $driver = DB::connection()->getDriverName();
        $periodExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', profit_records.calculated_at)"
            : "DATE_FORMAT(profit_records.calculated_at, '%Y-%m')";

        return $this->chinaOrderItemsQuery($filters)
            ->selectRaw("
                {$periodExpr} as period,
                SUM(order_items.quantity) as units,
                SUM(order_items.line_total) as revenue,
                SUM(COALESCE(order_cost_snapshots.total_cost, 0)) as landed_cost
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'period' => (string) $row->period,
                'units' => (int) $row->units,
                'revenue' => $this->money((float) $row->revenue),
                'landed_cost' => $this->money((float) $row->landed_cost),
            ])
            ->all();
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     */
    private function chinaOrderItemsQuery(array $filters): Builder
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $chinaCode = CommerceChannelCode::ChinaImport->value;
        $legacySource = CommerceChannelCode::ChinaImport->fulfillmentSource();

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('profit_records', 'profit_records.order_id', '=', 'orders.id')
            ->leftJoin('order_cost_snapshots', 'order_cost_snapshots.order_item_id', '=', 'order_items.id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('commerce_channels', 'commerce_channels.id', '=', 'products.commerce_channel_id')
            ->whereNull('orders.deleted_at')
            ->where('orders.is_demo', false)
            ->where(function (Builder $q) use ($chinaCode, $legacySource) {
                $q->where('commerce_channels.code', $chinaCode)
                    ->orWhere('products.fulfillment_source', $legacySource)
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(orders.commerce_channel_snapshot, '$.code')) = ?", [$chinaCode]);
            })
            ->when($from, fn (Builder $q) => $q->whereDate('profit_records.calculated_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('profit_records.calculated_at', '<=', $to));
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return array{from: string|null, to: string|null}
     */
    private function periodMeta(array $filters): array
    {
        return [
            'from' => filled($filters['from'] ?? null) ? (string) $filters['from'] : null,
            'to' => filled($filters['to'] ?? null) ? (string) $filters['to'] : null,
        ];
    }

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function percent(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
