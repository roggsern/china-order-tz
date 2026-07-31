<?php

namespace App\Services\Stores;

use App\Models\Admin;
use App\Models\Store;
use App\Services\Analytics\DTOs\AnalyticsFilter;
use App\Services\Analytics\RetailAnalyticsEngine;
use App\Services\Reporting\DTOs\ReportPeriod;

/**
 * Read-only store operations dashboard — composes existing analytics engines.
 */
class StoreOperationsDashboardService
{
    public function __construct(
        private readonly RetailAnalyticsEngine $retailAnalytics,
        private readonly ActiveStoreContext $storeContext,
    ) {}

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function dashboard(Admin $admin, Store $store, array $filters = []): array
    {
        $this->storeContext->assertCanView($admin, $store);

        $period = ReportPeriod::fromInput($filters['from'] ?? null, $filters['to'] ?? null, 30);
        $analyticsFilter = new AnalyticsFilter(
            period: $period,
            storeIds: [$store->id],
            posOnly: false,
        );

        $sales = $this->retailAnalytics->sales($analyticsFilter);
        $profit = $this->retailAnalytics->profit($analyticsFilter);
        $inventory = $this->retailAnalytics->inventory($analyticsFilter);
        $customers = $this->retailAnalytics->customers($analyticsFilter);

        return [
            'store' => [
                'id' => $store->id,
                'code' => $store->code,
                'name' => $store->name,
                'is_active' => $store->is_active,
            ],
            'period' => [
                'from' => $period->from->toDateString(),
                'to' => $period->to->toDateString(),
            ],
            'sales_summary' => $sales['summary'] ?? [],
            'orders_count' => $sales['summary']['orders_count'] ?? 0,
            'inventory_value' => $inventory['summary']['inventory_value'] ?? 0,
            'inventory_units' => $inventory['summary']['current_stock_units'] ?? 0,
            'low_stock_alerts' => $inventory['summary']['low_stock'] ?? 0,
            'top_products' => array_slice($sales['top_products'] ?? [], 0, 8),
            'customers' => [
                'walk_in' => $customers['summary']['walk_in_customers'] ?? 0,
                'registered' => $customers['summary']['registered_customers'] ?? 0,
                'returning' => $customers['summary']['returning_customers'] ?? 0,
                'new' => $customers['summary']['new_customers'] ?? 0,
                'top_customers' => array_slice($customers['top_customers'] ?? [], 0, 5),
            ],
            'profit_summary' => [
                'gross_revenue' => $profit['summary']['gross_revenue'] ?? 0,
                'net_revenue' => $profit['summary']['net_revenue'] ?? 0,
                'gross_profit' => $profit['summary']['profit'] ?? 0,
                'margin_percentage' => $profit['summary']['margin'] ?? 0,
                'refund_amount' => $profit['summary']['refund_amount'] ?? 0,
            ],
            'charts' => [
                'daily_sales' => $sales['series']['daily'] ?? null,
                'payment_breakdown' => $profit['payment_breakdown'] ?? null,
            ],
            'team_count' => $store->assignments()->where('is_active', true)->count(),
        ];
    }
}
