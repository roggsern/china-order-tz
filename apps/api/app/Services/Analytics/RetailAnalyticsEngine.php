<?php

namespace App\Services\Analytics;

use App\Enums\InventoryDisposition;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PosSessionStatus;
use App\Enums\RefundTransactionStatus;
use App\Enums\ReturnRequestStatus;
use App\Enums\SalesOrigin;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\ProfitRecord;
use App\Enums\LoyaltyAccountStatus;
use App\Enums\LoyaltyLedgerType;
use App\Enums\GrowthCampaignStatus;
use App\Enums\GrowthStage;
use App\Models\CustomerProfile;
use App\Models\GrowthCampaign;
use App\Models\GrowthSegment;
use App\Models\InventoryStockMovement;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyRedemption;
use App\Models\PromotionUsage;
use App\Models\RefundTransaction;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\VariantInventory;
use App\Services\Analytics\DTOs\AnalyticsFilter;
use App\Services\Analytics\Support\ChartSeries;
use App\Services\Reporting\ExportService;
use App\Services\Stores\ActiveStoreContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only retail intelligence. Aggregates Order / Payment / Profit / Inventory /
 * Returns / Sessions / Promotions / CRM fields — never duplicates business engines.
 */
class RetailAnalyticsEngine
{
    /** @var list<string> */
    public const EXPORT_TYPES = [
        'sales',
        'profit',
        'payments',
        'inventory',
        'returns',
        'customers',
        'stores',
        'sessions',
        'promotions',
        'loyalty',
        'growth',
    ];

    /** @var list<string> */
    public const AUDITED_EXPORT_TYPES = [
        'profit',
        'payments',
        'inventory',
        'customers',
    ];

    /** Short TTL for expensive dashboard/inventory summaries (seconds). */
    public const CACHE_TTL_SECONDS = 45;

    public function __construct(
        private readonly ActiveStoreContext $stores,
        private readonly ExportService $exports,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function resolveFilter(Admin $admin, array $input): AnalyticsFilter
    {
        return AnalyticsFilter::fromRequest($admin, $input, $this->stores);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(AnalyticsFilter $filter): array
    {
        return Cache::remember(
            $filter->cacheKey('dashboard'),
            self::CACHE_TTL_SECONDS,
            fn () => $this->buildDashboard($filter),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboard(AnalyticsFilter $filter): array
    {
        $today = new AnalyticsFilter(
            period: $filter->period->today(),
            storeIds: $filter->storeIds,
            cashierId: $filter->cashierId,
            customerId: $filter->customerId,
            categoryId: $filter->categoryId,
            productId: $filter->productId,
            paymentMethod: $filter->paymentMethod,
            promotionId: $filter->promotionId,
            returnReasonId: $filter->returnReasonId,
            posOnly: $filter->posOnly,
        );

        $salesToday = $this->salesTotals($today);
        $salesPeriod = $this->salesTotals($filter);
        $profitToday = $this->profitTotals($today);
        $returnsToday = $this->returnsTotals($today);
        $sessions = $this->sessionSnapshot($filter);

        $grossRevenue = $salesPeriod['gross_revenue'];
        $aov = $salesPeriod['orders_count'] > 0
            ? round($grossRevenue / $salesPeriod['orders_count'], 2)
            : 0.0;

        return [
            'period' => $this->periodPayload($filter),
            'scope' => [
                'store_ids' => $filter->storeIds,
                'cashier_id' => $filter->cashierId,
                'pos_only' => $filter->posOnly,
            ],
            'kpis' => [
                'todays_sales' => $salesToday['gross_revenue'],
                'todays_orders' => $salesToday['orders_count'],
                'todays_profit' => $profitToday['gross_profit'],
                'todays_refunds' => $returnsToday['refund_amount'],
                'todays_returns' => $returnsToday['returns_count'],
                'average_order_value' => $aov,
                'gross_margin' => $profitToday['margin_percentage'],
                'active_sessions' => $sessions['open_sessions'],
                'open_cash_drawers' => $sessions['open_sessions'],
                'low_stock_alerts' => $this->inventorySnapshot($filter)['low_stock'],
            ],
            'charts' => [
                ChartSeries::make('bar', 'daily_sales', 'Daily Sales', $this->bucketSales($filter, 'day'), 'Revenue'),
                ChartSeries::make('pie', 'payments', 'Payment Breakdown', ChartSeries::fromMap($this->paymentBreakdown($filter))),
            ],
            'drill_down' => [
                'stores' => '/admin/analytics/stores',
                'sessions' => '/admin/analytics/sessions',
                'returns' => '/admin/analytics/returns',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sales(AnalyticsFilter $filter): array
    {
        $totals = $this->salesTotals($filter);

        return [
            'period' => $this->periodPayload($filter),
            'summary' => $totals,
            'series' => [
                'hourly' => ChartSeries::make('line', 'hourly_sales', 'Hourly Sales', $this->bucketSales($filter, 'hour')),
                'daily' => ChartSeries::make('area', 'daily_sales', 'Daily Sales', $this->bucketSales($filter, 'day')),
                'weekly' => ChartSeries::make('bar', 'weekly_sales', 'Weekly Sales', $this->bucketSales($filter, 'week')),
                'monthly' => ChartSeries::make('bar', 'monthly_sales', 'Monthly Sales', $this->bucketSales($filter, 'month')),
                'yearly' => ChartSeries::make('bar', 'yearly_sales', 'Yearly Sales', $this->bucketSales($filter, 'year')),
            ],
            'by_store' => $this->salesByStore($filter),
            'by_cashier' => $this->salesByCashier($filter),
            'by_category' => $this->salesByCategory($filter),
            'by_product' => $this->salesByProduct($filter),
            'top_products' => array_slice($this->salesByProduct($filter), 0, 10),
            'top_categories' => array_slice($this->salesByCategory($filter), 0, 10),
            'top_stores' => array_slice($this->salesByStore($filter), 0, 10),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profit(AnalyticsFilter $filter): array
    {
        $totals = $this->profitTotals($filter);
        $sales = $this->salesTotals($filter);
        $returns = $this->returnsTotals($filter);
        $discount = $this->discountTotal($filter);
        $refundPct = $sales['gross_revenue'] > 0
            ? round(($returns['refund_amount'] / $sales['gross_revenue']) * 100, 2)
            : 0.0;

        return [
            'period' => $this->periodPayload($filter),
            'summary' => [
                'gross_revenue' => $sales['gross_revenue'],
                'net_revenue' => round($sales['gross_revenue'] - $returns['refund_amount'], 2),
                'profit' => $totals['gross_profit'],
                'margin' => $totals['margin_percentage'],
                'discount_cost' => $discount,
                'refund_amount' => $returns['refund_amount'],
                'refund_percentage' => $refundPct,
                'currency' => 'TZS',
            ],
            'payment_breakdown' => $this->paymentBreakdown($filter),
            'charts' => [
                ChartSeries::make('pie', 'payment_mix', 'Payment Mix', ChartSeries::fromMap($this->paymentBreakdown($filter))),
            ],
        ];
    }

    /**
     * Alias for financial dashboard (payments + profit).
     *
     * @return array<string, mixed>
     */
    public function payments(AnalyticsFilter $filter): array
    {
        return $this->profit($filter);
    }

    /**
     * @return array<string, mixed>
     */
    public function inventory(AnalyticsFilter $filter): array
    {
        return Cache::remember(
            $filter->cacheKey('inventory'),
            self::CACHE_TTL_SECONDS,
            function () use ($filter) {
                $snap = $this->inventorySnapshot($filter);
                $movers = $this->productVelocity($filter);
                $movements = InventoryStockMovement::query()
                    ->whereBetween('created_at', [$filter->period->from, $filter->period->to])
                    ->when($filter->hasStores(), fn ($q) => $q->whereIn('store_id', $filter->storeIds))
                    ->selectRaw('movement_type, COUNT(*) as cnt, COALESCE(SUM(ABS(quantity_change)),0) as units')
                    ->groupBy('movement_type')
                    ->get()
                    ->mapWithKeys(fn ($r) => [$r->movement_type instanceof \BackedEnum ? $r->movement_type->value : (string) $r->movement_type => [
                        'count' => (int) $r->cnt,
                        'units' => (int) $r->units,
                    ]])
                    ->all();

                $countVariance = (int) DB::table('inventory_count_lines')
                    ->join('inventory_count_sessions', 'inventory_count_sessions.id', '=', 'inventory_count_lines.inventory_count_session_id')
                    ->where('inventory_count_sessions.status', 'approved')
                    ->whereBetween('inventory_count_sessions.approved_at', [$filter->period->from, $filter->period->to])
                    ->when($filter->hasStores(), fn ($q) => $q->whereIn('inventory_count_sessions.store_id', $filter->storeIds))
                    ->sum(DB::raw('ABS(COALESCE(inventory_count_lines.difference, 0))'));

                return [
                    'period' => $this->periodPayload($filter),
                    'summary' => array_merge($snap, [
                        'stock_count_variance_units' => $countVariance,
                        'stock_movements' => $movements,
                    ]),
                    'fast_moving' => array_slice($movers['fast'], 0, 10),
                    'slow_moving' => array_slice($movers['slow'], 0, 10),
                    'dead_stock' => array_slice($movers['dead'], 0, 10),
                    'highest_stock_value' => array_slice($snap['by_value_desc'], 0, 10),
                    'lowest_stock_value' => array_slice($snap['by_value_asc'], 0, 10),
                    'charts' => [
                        ChartSeries::make('bar', 'stock_status', 'Stock Status', ChartSeries::fromMap([
                            'In Stock' => $snap['in_stock'],
                            'Low Stock' => $snap['low_stock'],
                            'Out of Stock' => $snap['out_of_stock'],
                        ])),
                    ],
                ];
            },
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function customers(AnalyticsFilter $filter): array
    {
        $orders = $this->orderQuery($filter)->get(['id', 'user_id', 'total', 'created_at']);
        $walkIn = $orders->whereNull('user_id')->count();
        $registered = $orders->whereNotNull('user_id')->count();
        $customerIds = $orders->pluck('user_id')->filter()->unique()->values();

        $priorCustomers = Order::query()
            ->real()
            ->when($filter->posOnly, fn ($q) => $q->where('sales_origin', SalesOrigin::Pos))
            ->when($filter->hasStores(), fn ($q) => $q->whereIn('store_id', $filter->storeIds))
            ->whereNotNull('user_id')
            ->where('created_at', '<', $filter->period->from)
            ->whereIn('user_id', $customerIds->all())
            ->distinct()
            ->pluck('user_id');

        $returning = $customerIds->intersect($priorCustomers)->count();
        $new = max(0, $customerIds->count() - $returning);

        $top = $orders
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->map(function ($group, $userId) {
                return [
                    'customer_id' => $userId,
                    'orders' => $group->count(),
                    'revenue' => round((float) $group->sum('total'), 2),
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->take(10)
            ->all();

        $frequency = $customerIds->count() > 0
            ? round($registered / max(1, $customerIds->count()), 2)
            : 0.0;

        return [
            'period' => $this->periodPayload($filter),
            'summary' => [
                'walk_in_customers' => $walkIn,
                'registered_customers' => $registered,
                'unique_registered' => $customerIds->count(),
                'returning_customers' => $returning,
                'new_customers' => $new,
                'purchase_frequency' => $frequency,
                'customer_lifetime_value' => null,
            ],
            'top_customers' => $top,
            'charts' => [
                ChartSeries::make('pie', 'customer_mix', 'Customer Mix', ChartSeries::fromMap([
                    'Walk-in' => $walkIn,
                    'Registered' => $registered,
                ])),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function promotions(AnalyticsFilter $filter): array
    {
        $usageQuery = PromotionUsage::query()
            ->whereBetween('used_at', [$filter->period->from, $filter->period->to])
            ->whereHas('order', function (Builder $q) use ($filter) {
                $q->real();
                if ($filter->posOnly) {
                    $q->where('sales_origin', SalesOrigin::Pos);
                }
                if ($filter->hasStores()) {
                    $q->whereIn('store_id', $filter->storeIds);
                }
                if ($filter->cashierId) {
                    $q->whereHas('posSession', fn ($s) => $s->where('admin_id', $filter->cashierId));
                }
            });

        if ($filter->promotionId) {
            $usageQuery->where('promotion_id', $filter->promotionId);
        }

        $discountCost = (float) (clone $usageQuery)->sum('discount_amount');
        $usageCount = (clone $usageQuery)->count();
        $ordersWithPromo = (int) (clone $usageQuery)->distinct('order_id')->count('order_id');
        $sales = $this->salesTotals($filter);
        $conversion = $sales['orders_count'] > 0
            ? round(($ordersWithPromo / $sales['orders_count']) * 100, 2)
            : 0.0;

        $top = PromotionUsage::query()
            ->selectRaw('promotion_id, COUNT(*) as usage_count, COALESCE(SUM(discount_amount),0) as discount_amount')
            ->whereBetween('used_at', [$filter->period->from, $filter->period->to])
            ->whereHas('order', function (Builder $q) use ($filter) {
                $q->real();
                if ($filter->posOnly) {
                    $q->where('sales_origin', SalesOrigin::Pos);
                }
                if ($filter->hasStores()) {
                    $q->whereIn('store_id', $filter->storeIds);
                }
            })
            ->when($filter->promotionId, fn ($q) => $q->where('promotion_id', $filter->promotionId))
            ->groupBy('promotion_id')
            ->orderByDesc('usage_count')
            ->limit(10)
            ->with('promotion:id,name,code')
            ->get()
            ->map(fn ($row) => [
                'promotion_id' => $row->promotion_id,
                'name' => $row->promotion?->name,
                'code' => $row->promotion?->code,
                'usage_count' => (int) $row->usage_count,
                'discount_amount' => round((float) $row->discount_amount, 2),
            ])
            ->all();

        return [
            'period' => $this->periodPayload($filter),
            'summary' => [
                'promotion_usage' => $usageCount,
                'promotion_revenue' => $sales['gross_revenue'],
                'discount_cost' => round($discountCost, 2),
                'orders_with_promotion' => $ordersWithPromo,
                'promotion_conversion' => $conversion,
            ],
            'top_promotions' => $top,
            'charts' => [
                ChartSeries::make(
                    'bar',
                    'top_promotions',
                    'Top Promotions',
                    array_map(fn ($r) => [
                        'x' => $r['code'] ?? $r['name'] ?? 'n/a',
                        'y' => $r['usage_count'],
                        'label' => $r['code'] ?? 'n/a',
                    ], $top),
                ),
            ],
        ];
    }

    /**
     * Growth / engagement indicators. Read-only orchestration metrics.
     *
     * @return array<string, mixed>
     */
    public function growth(AnalyticsFilter $filter): array
    {
        $campaigns = GrowthCampaign::query()
            ->whereBetween('created_at', [$filter->period->from, $filter->period->to])
            ->when($filter->hasStores(), fn ($q) => $q->whereIn('store_id', $filter->storeIds))
            ->get();

        $completed = $campaigns->where('status', GrowthCampaignStatus::Completed);
        $sent = (int) $completed->sum('sent_count');
        $purchased = (int) $completed->sum('purchased_count');
        $revenue = (float) $completed->sum('revenue_generated');

        $stages = CustomerProfile::query()
            ->forCustomers()
            ->selectRaw('growth_stage, COUNT(*) as cnt')
            ->groupBy('growth_stage')
            ->pluck('cnt', 'growth_stage')
            ->all();

        $customersWithOrders = CustomerProfile::query()
            ->forCustomers()
            ->whereHas('metrics', fn ($m) => $m->where('total_orders', '>', 0))
            ->count();
        $repeat = CustomerProfile::query()
            ->forCustomers()
            ->whereHas('metrics', fn ($m) => $m->where('total_orders', '>', 1))
            ->count();

        return [
            'period' => $this->periodPayload($filter),
            'summary' => [
                'active_segments' => GrowthSegment::query()->where('is_active', true)->count(),
                'campaigns_in_period' => $campaigns->count(),
                'campaign_revenue' => round($revenue, 2),
                'campaign_conversion' => $sent > 0 ? round(($purchased / $sent) * 100, 2) : 0.0,
                'repeat_purchase_rate' => $customersWithOrders > 0
                    ? round(($repeat / $customersWithOrders) * 100, 2)
                    : 0.0,
                'retention_indicator' => (int) ($stages[GrowthStage::Active->value] ?? 0)
                    + (int) ($stages[GrowthStage::Vip->value] ?? 0),
            ],
            'lifecycle_distribution' => [
                'new' => (int) ($stages[GrowthStage::New->value] ?? 0),
                'active' => (int) ($stages[GrowthStage::Active->value] ?? 0),
                'vip' => (int) ($stages[GrowthStage::Vip->value] ?? 0),
                'inactive' => (int) ($stages[GrowthStage::Inactive->value] ?? 0),
                'winback' => (int) ($stages[GrowthStage::Winback->value] ?? 0),
            ],
            'charts' => [
                ChartSeries::make(
                    'pie',
                    'lifecycle',
                    'Customer Lifecycle',
                    ChartSeries::fromMap([
                        'New' => (int) ($stages[GrowthStage::New->value] ?? 0),
                        'Active' => (int) ($stages[GrowthStage::Active->value] ?? 0),
                        'VIP' => (int) ($stages[GrowthStage::Vip->value] ?? 0),
                        'Inactive' => (int) ($stages[GrowthStage::Inactive->value] ?? 0),
                        'Win-back' => (int) ($stages[GrowthStage::Winback->value] ?? 0),
                    ]),
                ),
            ],
        ];
    }

    /**
     * Loyalty retention indicators (points, tiers, rewards). Read-only.
     *
     * @return array<string, mixed>
     */
    public function loyalty(AnalyticsFilter $filter): array
    {
        $active = LoyaltyAccount::query()
            ->where('status', LoyaltyAccountStatus::Active)
            ->count();

        $issued = (int) LoyaltyLedgerEntry::query()
            ->where('entry_type', LoyaltyLedgerType::Earn)
            ->whereBetween('created_at', [$filter->period->from, $filter->period->to])
            ->sum('points');

        $redeemed = (int) abs((int) LoyaltyLedgerEntry::query()
            ->where('entry_type', LoyaltyLedgerType::Redeem)
            ->whereBetween('created_at', [$filter->period->from, $filter->period->to])
            ->sum('points'));

        $rewardUsage = LoyaltyRedemption::query()
            ->whereBetween('issued_at', [$filter->period->from, $filter->period->to])
            ->count();

        $tierDist = LoyaltyAccount::query()
            ->selectRaw('loyalty_tier_id, COUNT(*) as cnt')
            ->groupBy('loyalty_tier_id')
            ->with('tier:id,code,name')
            ->get()
            ->map(fn ($r) => [
                'tier' => $r->tier?->name ?? 'Unassigned',
                'code' => $r->tier?->code,
                'customers' => (int) $r->cnt,
            ])
            ->all();

        $top = LoyaltyAccount::query()
            ->with(['profile.user:id,name,email', 'tier:id,code,name'])
            ->orderByDesc('lifetime_points')
            ->limit(10)
            ->get()
            ->map(fn (LoyaltyAccount $a) => [
                'loyalty_number' => $a->loyalty_number,
                'customer' => $a->profile?->user?->name,
                'points_balance' => (int) $a->points_balance,
                'lifetime_points' => (int) $a->lifetime_points,
                'tier' => $a->tier?->name,
            ])
            ->all();

        $enrolledInPeriod = LoyaltyAccount::query()
            ->whereBetween('enrolled_at', [$filter->period->from, $filter->period->to])
            ->count();

        return [
            'period' => $this->periodPayload($filter),
            'summary' => [
                'active_loyalty_customers' => $active,
                'points_issued' => $issued,
                'points_redeemed' => $redeemed,
                'reward_usage' => $rewardUsage,
                'new_enrollments' => $enrolledInPeriod,
                'retention_indicator' => $active > 0
                    ? round(($redeemed > 0 ? min(100, ($rewardUsage / max(1, $active)) * 100) : 0), 2)
                    : 0.0,
            ],
            'tier_distribution' => $tierDist,
            'top_loyalty_customers' => $top,
            'charts' => [
                ChartSeries::make(
                    'pie',
                    'tier_distribution',
                    'Tier Distribution',
                    ChartSeries::fromMap(collect($tierDist)->mapWithKeys(
                        fn ($r) => [$r['tier'] => $r['customers']]
                    )->all()),
                ),
                ChartSeries::make(
                    'bar',
                    'top_loyalty',
                    'Top Loyalty Customers',
                    array_map(fn ($r) => [
                        'x' => $r['customer'] ?? $r['loyalty_number'],
                        'y' => $r['lifetime_points'],
                        'label' => $r['loyalty_number'],
                    ], $top),
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function returns(AnalyticsFilter $filter): array
    {
        $totals = $this->returnsTotals($filter);
        $sales = $this->salesTotals($filter);
        $returnRate = $sales['orders_count'] > 0
            ? round(($totals['returns_count'] / $sales['orders_count']) * 100, 2)
            : 0.0;

        $reasons = ReturnRequest::query()
            ->selectRaw('return_reason_id, COUNT(*) as cnt')
            ->where('status', ReturnRequestStatus::Completed->value)
            ->whereBetween('completed_at', [$filter->period->from, $filter->period->to])
            ->when($filter->hasStores(), fn ($q) => $q->whereIn('store_id', $filter->storeIds))
            ->when($filter->cashierId, fn ($q) => $q->where('processed_by', $filter->cashierId))
            ->when($filter->returnReasonId, fn ($q) => $q->where('return_reason_id', $filter->returnReasonId))
            ->when($filter->posOnly, fn ($q) => $q->where('sales_origin', SalesOrigin::Pos->value))
            ->groupBy('return_reason_id')
            ->with('returnReason:id,code,name')
            ->get()
            ->map(fn ($r) => [
                'return_reason_id' => $r->return_reason_id,
                'code' => $r->returnReason?->code,
                'name' => $r->returnReason?->name ?? 'Other',
                'count' => (int) $r->cnt,
            ])
            ->all();

        $byProduct = ReturnItem::query()
            ->selectRaw('order_items.product_id, MAX(order_items.product_name_snapshot) as product_name, SUM(return_items.quantity) as qty')
            ->join('return_requests', 'return_requests.id', '=', 'return_items.return_request_id')
            ->join('order_items', 'order_items.id', '=', 'return_items.order_item_id')
            ->where('return_requests.status', ReturnRequestStatus::Completed->value)
            ->whereBetween('return_requests.completed_at', [$filter->period->from, $filter->period->to])
            ->when($filter->hasStores(), fn ($q) => $q->whereIn('return_requests.store_id', $filter->storeIds))
            ->when($filter->posOnly, fn ($q) => $q->where('return_requests.sales_origin', SalesOrigin::Pos->value))
            ->groupBy('order_items.product_id')
            ->orderByDesc('qty')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'product_id' => $r->product_id,
                'product_name' => $r->product_name,
                'quantity' => (int) $r->qty,
            ])
            ->all();

        $byStore = ReturnRequest::query()
            ->selectRaw('store_id, COUNT(*) as cnt, COALESCE(SUM(refund_total),0) as refund_total')
            ->where('status', ReturnRequestStatus::Completed->value)
            ->whereBetween('completed_at', [$filter->period->from, $filter->period->to])
            ->when($filter->hasStores(), fn ($q) => $q->whereIn('store_id', $filter->storeIds))
            ->when($filter->posOnly, fn ($q) => $q->where('sales_origin', SalesOrigin::Pos->value))
            ->groupBy('store_id')
            ->with('store:id,code,name')
            ->get()
            ->map(fn ($r) => [
                'store_id' => $r->store_id,
                'store_code' => $r->store?->code,
                'store_name' => $r->store?->name,
                'returns' => (int) $r->cnt,
                'refund_amount' => round((float) $r->refund_total, 2),
            ])
            ->all();

        $byCashier = ReturnRequest::query()
            ->selectRaw('processed_by, COUNT(*) as cnt, COALESCE(SUM(refund_total),0) as refund_total')
            ->where('status', ReturnRequestStatus::Completed->value)
            ->whereBetween('completed_at', [$filter->period->from, $filter->period->to])
            ->when($filter->hasStores(), fn ($q) => $q->whereIn('store_id', $filter->storeIds))
            ->when($filter->cashierId, fn ($q) => $q->where('processed_by', $filter->cashierId))
            ->when($filter->posOnly, fn ($q) => $q->where('sales_origin', SalesOrigin::Pos->value))
            ->whereNotNull('processed_by')
            ->groupBy('processed_by')
            ->with('processor:id,name')
            ->get()
            ->map(fn ($r) => [
                'cashier_id' => $r->processed_by,
                'cashier_name' => $r->processor?->name,
                'returns' => (int) $r->cnt,
                'refund_amount' => round((float) $r->refund_total, 2),
            ])
            ->all();

        return [
            'period' => $this->periodPayload($filter),
            'summary' => array_merge($totals, [
                'return_rate' => $returnRate,
            ]),
            'by_reason' => $reasons,
            'by_product' => $byProduct,
            'by_store' => $byStore,
            'by_cashier' => $byCashier,
            'charts' => [
                ChartSeries::make(
                    'pie',
                    'return_reasons',
                    'Return Reasons',
                    array_map(fn ($r) => [
                        'x' => $r['name'],
                        'y' => $r['count'],
                        'label' => $r['name'],
                    ], $reasons),
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sessions(AnalyticsFilter $filter): array
    {
        $snap = $this->sessionSnapshot($filter);
        $query = PosSession::query()
            ->when($filter->hasStores(), fn ($q) => $q->whereIn('store_id', $filter->storeIds))
            ->when($filter->cashierId, fn ($q) => $q->where('admin_id', $filter->cashierId))
            ->where(function ($q) use ($filter) {
                $q->whereBetween('opened_at', [$filter->period->from, $filter->period->to])
                    ->orWhereBetween('closed_at', [$filter->period->from, $filter->period->to]);
            });

        $rows = (clone $query)->with(['admin:id,name', 'store:id,code,name'])->get();
        $closed = $rows->where('status', PosSessionStatus::Closed);
        $avgSales = $closed->count() > 0
            ? round((float) $closed->sum(fn ($s) => (float) ($s->payment_breakdown['total'] ?? $s->cash_sales ?? 0)) / $closed->count(), 2)
            : 0.0;

        $durations = $closed->map(function (PosSession $s) {
            if ($s->opened_at === null || $s->closed_at === null) {
                return null;
            }

            return $s->opened_at->diffInMinutes($s->closed_at);
        })->filter();

        $productivity = $rows
            ->groupBy('admin_id')
            ->map(function ($group, $adminId) {
                /** @var PosSession $first */
                $first = $group->first();
                $sales = (float) $group->sum(fn ($s) => (float) ($s->cash_sales ?? 0));

                return [
                    'cashier_id' => $adminId,
                    'cashier_name' => $first->admin?->name,
                    'sessions' => $group->count(),
                    'cash_sales' => round($sales, 2),
                    'avg_variance' => round((float) $group->avg('variance_amount'), 2),
                ];
            })
            ->sortByDesc('cash_sales')
            ->values()
            ->all();

        return [
            'period' => $this->periodPayload($filter),
            'summary' => array_merge($snap, [
                'average_session_sales' => $avgSales,
                'average_duration_minutes' => $durations->count() > 0 ? round((float) $durations->avg(), 1) : 0.0,
                'total_cash_variance' => round((float) $closed->sum('variance_amount'), 2),
            ]),
            'cashier_productivity' => $productivity,
            'charts' => [
                ChartSeries::make('bar', 'cashier_sales', 'Cashier Productivity', array_map(
                    fn ($r) => [
                        'x' => $r['cashier_name'] ?? 'Cashier',
                        'y' => $r['cash_sales'],
                        'label' => $r['cashier_name'] ?? 'Cashier',
                    ],
                    array_slice($productivity, 0, 10),
                )),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stores(AnalyticsFilter $filter): array
    {
        $byStore = $this->salesByStore($filter);
        $profitByStore = ProfitRecord::query()
            ->selectRaw('orders.store_id, COALESCE(SUM(profit_records.revenue),0) as revenue, COALESCE(SUM(profit_records.gross_profit),0) as gross_profit')
            ->join('orders', 'orders.id', '=', 'profit_records.order_id')
            ->where('orders.is_demo', false)
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.created_at', [$filter->period->from, $filter->period->to])
            ->when($filter->posOnly, fn ($q) => $q->where('orders.sales_origin', SalesOrigin::Pos->value))
            ->when($filter->hasStores(), fn ($q) => $q->whereIn('orders.store_id', $filter->storeIds))
            ->groupBy('orders.store_id')
            ->get()
            ->keyBy('store_id');

        $returnsByStore = collect($this->returns($filter)['by_store'])->keyBy('store_id');
        $inv = collect($this->inventoryValueByStore($filter))->keyBy('store_id');

        $previous = new AnalyticsFilter(
            period: new \App\Services\Reporting\DTOs\ReportPeriod(
                $filter->period->from->copy()->subDays($filter->period->from->diffInDays($filter->period->to) + 1),
                $filter->period->from->copy()->subSecond(),
            ),
            storeIds: $filter->storeIds,
            cashierId: $filter->cashierId,
            posOnly: $filter->posOnly,
        );
        $prevSales = collect($this->salesByStore($previous))->keyBy('store_id');

        $ranking = collect($byStore)->map(function (array $row) use ($profitByStore, $returnsByStore, $inv, $prevSales) {
            $sid = $row['store_id'];
            $profit = $profitByStore->get($sid);
            $ret = $returnsByStore->get($sid);
            $inventory = $inv->get($sid);
            $prev = (float) ($prevSales->get($sid)['revenue'] ?? 0);
            $growth = $prev > 0 ? round((($row['revenue'] - $prev) / $prev) * 100, 2) : null;

            return [
                'store_id' => $sid,
                'store_code' => $row['store_code'],
                'store_name' => $row['store_name'],
                'sales' => $row['revenue'],
                'orders' => $row['orders'],
                'average_basket' => $row['average_basket'],
                'customer_count' => $row['customers'],
                'profit' => round((float) ($profit->gross_profit ?? 0), 2),
                'returns' => (int) ($ret['returns'] ?? 0),
                'refunds' => (float) ($ret['refund_amount'] ?? 0),
                'inventory_value' => (float) ($inventory['inventory_value'] ?? 0),
                'growth_percentage' => $growth,
            ];
        })
            ->sortByDesc('sales')
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;

                return $row;
            })
            ->all();

        return [
            'period' => $this->periodPayload($filter),
            'ranking' => $ranking,
            'charts' => [
                ChartSeries::make('bar', 'store_sales', 'Store Sales', array_map(
                    fn ($r) => [
                        'x' => $r['store_code'] ?? $r['store_name'],
                        'y' => $r['sales'],
                        'label' => $r['store_code'] ?? '',
                    ],
                    $ranking,
                )),
            ],
        ];
    }

    /**
     * @return array{type: string, columns: list<string>, rows: list<array<string, mixed>>, period: array<string, string>}
     */
    public function tabular(string $type, AnalyticsFilter $filter): array
    {
        $type = strtolower($type);

        return match ($type) {
            'sales' => $this->tableFromRows('sales', ['store_code', 'store_name', 'orders', 'revenue', 'average_basket', 'customers'], $this->salesByStore($filter), $filter),
            'profit' => $this->tableFromAssoc('profit', $this->profit($filter)['summary'], $filter),
            'payments' => $this->tableFromMap('payments', $this->paymentBreakdown($filter), $filter),
            'inventory' => $this->tableFromRows('inventory', ['sku', 'product_name', 'on_hand', 'available', 'unit_cost', 'stock_value', 'status'], $this->inventoryRows($filter), $filter),
            'returns' => $this->tableFromRows('returns', ['store_code', 'store_name', 'returns', 'refund_amount'], $this->returns($filter)['by_store'], $filter),
            'customers' => $this->tableFromRows('customers', ['customer_id', 'orders', 'revenue'], $this->customers($filter)['top_customers'], $filter),
            'stores' => $this->tableFromRows('stores', ['rank', 'store_code', 'store_name', 'sales', 'profit', 'returns', 'refunds', 'average_basket', 'inventory_value', 'growth_percentage'], $this->stores($filter)['ranking'], $filter),
            'sessions' => $this->tableFromRows('sessions', ['cashier_name', 'sessions', 'cash_sales', 'avg_variance'], $this->sessions($filter)['cashier_productivity'], $filter),
            'promotions' => $this->tableFromRows('promotions', ['code', 'name', 'usage_count', 'discount_amount'], $this->promotions($filter)['top_promotions'], $filter),
            'loyalty' => $this->tableFromRows(
                'loyalty',
                ['loyalty_number', 'customer', 'points_balance', 'lifetime_points', 'tier'],
                $this->loyalty($filter)['top_loyalty_customers'],
                $filter,
            ),
            default => throw new \InvalidArgumentException("Unknown analytics export type [{$type}]."),
        };
    }

    public function export(string $type, string $format, AnalyticsFilter $filter): StreamedResponse
    {
        return $this->exports->export($this->tabular($type, $filter), $format);
    }

    public function isAuditedExport(string $type): bool
    {
        return in_array(strtolower($type), self::AUDITED_EXPORT_TYPES, true);
    }

    /**
     * @return array{from: string, to: string}
     */
    private function periodPayload(AnalyticsFilter $filter): array
    {
        return [
            'from' => $filter->period->from->toDateString(),
            'to' => $filter->period->to->toDateString(),
        ];
    }

    /**
     * @return Builder<Order>
     */
    private function orderQuery(AnalyticsFilter $filter): Builder
    {
        $q = Order::query()
            ->real()
            ->whereBetween('created_at', [$filter->period->from, $filter->period->to]);

        if ($filter->posOnly) {
            $q->where('sales_origin', SalesOrigin::Pos);
        }

        if ($filter->hasStores()) {
            $q->whereIn('store_id', $filter->storeIds);
        }

        if ($filter->cashierId) {
            $q->whereHas('posSession', fn ($s) => $s->where('admin_id', $filter->cashierId));
        }

        if ($filter->customerId) {
            $q->where('user_id', $filter->customerId);
        }

        if ($filter->productId) {
            $q->whereHas('items', fn ($i) => $i->where('product_id', $filter->productId));
        }

        if ($filter->categoryId) {
            $q->whereHas('items.product', fn ($p) => $p->where('category_id', $filter->categoryId));
        }

        if ($filter->paymentMethod) {
            $method = $filter->paymentMethod;
            $q->whereHas('payments', function ($p) use ($method) {
                $p->where('status', PaymentStatus::Paid)
                    ->where(function ($inner) use ($method) {
                        $inner->where('reference', $method)
                            ->orWhere('method', strtolower($method))
                            ->orWhere('metadata->payment_method_code', $method);
                    });
            });
        }

        return $q;
    }

    /**
     * @return list<string>
     */
    private function paidStatuses(): array
    {
        return [
            OrderStatus::Paid->value,
            OrderStatus::Confirmed->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
            OrderStatus::Completed->value,
        ];
    }

    /**
     * @return array{gross_revenue: float, orders_count: int, units: int}
     */
    private function salesTotals(AnalyticsFilter $filter): array
    {
        $q = $this->orderQuery($filter)->whereIn('status', $this->paidStatuses());

        return [
            'gross_revenue' => round((float) (clone $q)->sum('total'), 2),
            'orders_count' => (clone $q)->count(),
            'units' => (int) OrderItem::query()
                ->whereIn('order_id', (clone $q)->select('id'))
                ->sum('quantity'),
        ];
    }

    /**
     * @return array{revenue: float, total_cost: float, gross_profit: float, margin_percentage: float, orders_count: int}
     */
    private function profitTotals(AnalyticsFilter $filter): array
    {
        $agg = ProfitRecord::query()
            ->join('orders', 'orders.id', '=', 'profit_records.order_id')
            ->where('orders.is_demo', false)
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.created_at', [$filter->period->from, $filter->period->to])
            ->when($filter->posOnly, fn ($q) => $q->where('orders.sales_origin', SalesOrigin::Pos->value))
            ->when($filter->hasStores(), fn ($q) => $q->whereIn('orders.store_id', $filter->storeIds))
            ->when($filter->cashierId, fn ($q) => $q->whereExists(function ($sub) use ($filter) {
                $sub->select(DB::raw(1))
                    ->from('pos_sessions')
                    ->whereColumn('pos_sessions.id', 'orders.pos_session_id')
                    ->where('pos_sessions.admin_id', $filter->cashierId);
            }))
            ->selectRaw('
                COALESCE(SUM(profit_records.revenue),0) as revenue,
                COALESCE(SUM(profit_records.total_cost),0) as total_cost,
                COALESCE(SUM(profit_records.gross_profit),0) as gross_profit,
                COUNT(*) as orders_count
            ')
            ->first();

        $revenue = (float) ($agg->revenue ?? 0);
        $profit = (float) ($agg->gross_profit ?? 0);

        return [
            'revenue' => round($revenue, 2),
            'total_cost' => round((float) ($agg->total_cost ?? 0), 2),
            'gross_profit' => round($profit, 2),
            'margin_percentage' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0.0,
            'orders_count' => (int) ($agg->orders_count ?? 0),
        ];
    }

    /**
     * @return array{returns_count: int, exchange_count: int, refund_amount: float, damaged_returns: int, inspection_queue: int}
     */
    private function returnsTotals(AnalyticsFilter $filter): array
    {
        $base = ReturnRequest::query()
            ->where('status', ReturnRequestStatus::Completed->value)
            ->whereBetween('completed_at', [$filter->period->from, $filter->period->to])
            ->when($filter->hasStores(), fn ($q) => $q->whereIn('store_id', $filter->storeIds))
            ->when($filter->cashierId, fn ($q) => $q->where('processed_by', $filter->cashierId))
            ->when($filter->returnReasonId, fn ($q) => $q->where('return_reason_id', $filter->returnReasonId))
            ->when($filter->posOnly, fn ($q) => $q->where('sales_origin', SalesOrigin::Pos->value));

        $refundAmount = (float) (clone $base)->sum('refund_total');
        $completedRefunds = RefundTransaction::query()
            ->where('status', RefundTransactionStatus::Completed->value)
            ->whereBetween('created_at', [$filter->period->from, $filter->period->to])
            ->whereHas('returnRequest', function ($q) use ($filter) {
                if ($filter->hasStores()) {
                    $q->whereIn('store_id', $filter->storeIds);
                }
                if ($filter->posOnly) {
                    $q->where('sales_origin', SalesOrigin::Pos->value);
                }
            })
            ->sum('amount');

        $damaged = ReturnItem::query()
            ->where('inventory_disposition', InventoryDisposition::Damaged->value)
            ->whereHas('returnRequest', function ($q) use ($filter) {
                $q->where('status', ReturnRequestStatus::Completed->value)
                    ->whereBetween('completed_at', [$filter->period->from, $filter->period->to]);
                if ($filter->hasStores()) {
                    $q->whereIn('store_id', $filter->storeIds);
                }
                if ($filter->posOnly) {
                    $q->where('sales_origin', SalesOrigin::Pos->value);
                }
            })
            ->count();

        $inspection = ReturnItem::query()
            ->where('inventory_disposition', InventoryDisposition::Inspection->value)
            ->whereHas('returnRequest', function ($q) use ($filter) {
                $q->whereIn('status', [
                    ReturnRequestStatus::Completed->value,
                    ReturnRequestStatus::Approved->value,
                    ReturnRequestStatus::Requested->value,
                ]);
                if ($filter->hasStores()) {
                    $q->whereIn('store_id', $filter->storeIds);
                }
            })
            ->count();

        return [
            'returns_count' => (clone $base)->count(),
            'exchange_count' => (clone $base)->where('return_type', \App\Enums\PosReturnType::Exchange->value)->count(),
            'refund_amount' => round(max($refundAmount, (float) $completedRefunds), 2),
            'damaged_returns' => $damaged,
            'inspection_queue' => $inspection,
        ];
    }

    private function discountTotal(AnalyticsFilter $filter): float
    {
        return round((float) $this->orderQuery($filter)->sum('discount_amount'), 2);
    }

    /**
     * @return array<string, float>
     */
    private function paymentBreakdown(AnalyticsFilter $filter): array
    {
        $payments = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereHas('order', function (Builder $q) use ($filter) {
                $q->real()->whereBetween('created_at', [$filter->period->from, $filter->period->to]);
                if ($filter->posOnly) {
                    $q->where('sales_origin', SalesOrigin::Pos);
                }
                if ($filter->hasStores()) {
                    $q->whereIn('store_id', $filter->storeIds);
                }
                if ($filter->cashierId) {
                    $q->whereHas('posSession', fn ($s) => $s->where('admin_id', $filter->cashierId));
                }
            })
            ->get(['amount', 'reference', 'method', 'metadata']);

        $map = [];
        foreach ($payments as $payment) {
            $code = strtoupper((string) (
                data_get($payment->metadata, 'payment_method_code')
                ?: $payment->reference
                ?: ($payment->method?->value ?? 'UNKNOWN')
            ));
            if ($filter->paymentMethod !== null && $code !== $filter->paymentMethod) {
                continue;
            }
            $map[$code] = ($map[$code] ?? 0) + (float) $payment->amount;
        }

        return collect($map)->map(fn ($v) => round($v, 2))->all();
    }

    /**
     * @return list<array{x: string, y: float, label: string}>
     */
    private function bucketSales(AnalyticsFilter $filter, string $grain): array
    {
        $orders = $this->orderQuery($filter)
            ->whereIn('status', $this->paidStatuses())
            ->get(['total', 'created_at']);

        $buckets = [];
        foreach ($orders as $order) {
            /** @var Carbon $at */
            $at = $order->created_at;
            $key = match ($grain) {
                'hour' => $at->format('Y-m-d H:00'),
                'day' => $at->toDateString(),
                'week' => $at->copy()->startOfWeek()->toDateString(),
                'month' => $at->format('Y-m'),
                'year' => $at->format('Y'),
                default => $at->toDateString(),
            };
            $buckets[$key] = ($buckets[$key] ?? 0) + (float) $order->total;
        }

        ksort($buckets);

        return array_map(
            fn ($k, $v) => ['x' => $k, 'y' => round($v, 2), 'label' => $k],
            array_keys($buckets),
            array_values($buckets),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesByStore(AnalyticsFilter $filter): array
    {
        $rows = $this->orderQuery($filter)
            ->whereIn('status', $this->paidStatuses())
            ->selectRaw('store_id, COUNT(*) as orders, COALESCE(SUM(total),0) as revenue, COUNT(DISTINCT user_id) as customers')
            ->groupBy('store_id')
            ->with('store:id,code,name')
            ->get();

        return $rows->map(function ($r) {
            $orders = (int) $r->orders;
            $revenue = round((float) $r->revenue, 2);

            return [
                'store_id' => $r->store_id,
                'store_code' => $r->store?->code,
                'store_name' => $r->store?->name,
                'orders' => $orders,
                'revenue' => $revenue,
                'average_basket' => $orders > 0 ? round($revenue / $orders, 2) : 0.0,
                'customers' => (int) $r->customers,
            ];
        })->sortByDesc('revenue')->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesByCashier(AnalyticsFilter $filter): array
    {
        $rows = Order::query()
            ->real()
            ->whereBetween('orders.created_at', [$filter->period->from, $filter->period->to])
            ->whereIn('orders.status', $this->paidStatuses())
            ->when($filter->posOnly, fn ($q) => $q->where('orders.sales_origin', SalesOrigin::Pos))
            ->when($filter->hasStores(), fn ($q) => $q->whereIn('orders.store_id', $filter->storeIds))
            ->join('pos_sessions', 'pos_sessions.id', '=', 'orders.pos_session_id')
            ->when($filter->cashierId, fn ($q) => $q->where('pos_sessions.admin_id', $filter->cashierId))
            ->selectRaw('pos_sessions.admin_id as cashier_id, COUNT(*) as orders, COALESCE(SUM(orders.total),0) as revenue')
            ->groupBy('pos_sessions.admin_id')
            ->get();

        $admins = Admin::query()->whereIn('id', $rows->pluck('cashier_id')->filter())->get()->keyBy('id');

        return $rows->map(fn ($r) => [
            'cashier_id' => $r->cashier_id,
            'cashier_name' => $admins->get($r->cashier_id)?->name,
            'orders' => (int) $r->orders,
            'revenue' => round((float) $r->revenue, 2),
        ])->sortByDesc('revenue')->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesByProduct(AnalyticsFilter $filter): array
    {
        $q = OrderItem::query()
            ->selectRaw('
                order_items.product_id,
                MAX(order_items.product_name_snapshot) as product_name,
                MAX(order_items.sku_snapshot) as sku,
                SUM(order_items.quantity) as units,
                COALESCE(SUM(order_items.line_total),0) as revenue
            ')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.is_demo', false)
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.status', $this->paidStatuses())
            ->whereBetween('orders.created_at', [$filter->period->from, $filter->period->to])
            ->when($filter->posOnly, fn ($q) => $q->where('orders.sales_origin', SalesOrigin::Pos->value))
            ->when($filter->hasStores(), fn ($q) => $q->whereIn('orders.store_id', $filter->storeIds))
            ->when($filter->productId, fn ($q) => $q->where('order_items.product_id', $filter->productId))
            ->when($filter->categoryId, function ($q) use ($filter) {
                $q->join('products', 'products.id', '=', 'order_items.product_id')
                    ->where('products.category_id', $filter->categoryId);
            })
            ->when($filter->cashierId, fn ($q) => $q->whereExists(function ($sub) use ($filter) {
                $sub->select(DB::raw(1))
                    ->from('pos_sessions')
                    ->whereColumn('pos_sessions.id', 'orders.pos_session_id')
                    ->where('pos_sessions.admin_id', $filter->cashierId);
            }))
            ->groupBy('order_items.product_id')
            ->orderByDesc('revenue')
            ->limit(50)
            ->get();

        return $q->map(fn ($r) => [
            'product_id' => $r->product_id,
            'product_name' => $r->product_name,
            'sku' => $r->sku,
            'units' => (int) $r->units,
            'revenue' => round((float) $r->revenue, 2),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesByCategory(AnalyticsFilter $filter): array
    {
        $rows = OrderItem::query()
            ->selectRaw('
                products.category_id,
                MAX(categories.name) as category_name,
                SUM(order_items.quantity) as units,
                COALESCE(SUM(order_items.line_total),0) as revenue
            ')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.is_demo', false)
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.status', $this->paidStatuses())
            ->whereBetween('orders.created_at', [$filter->period->from, $filter->period->to])
            ->when($filter->posOnly, fn ($q) => $q->where('orders.sales_origin', SalesOrigin::Pos->value))
            ->when($filter->hasStores(), fn ($q) => $q->whereIn('orders.store_id', $filter->storeIds))
            ->when($filter->categoryId, fn ($q) => $q->where('products.category_id', $filter->categoryId))
            ->groupBy('products.category_id')
            ->orderByDesc('revenue')
            ->limit(50)
            ->get();

        return $rows->map(fn ($r) => [
            'category_id' => $r->category_id,
            'category_name' => $r->category_name ?? 'Uncategorized',
            'units' => (int) $r->units,
            'revenue' => round((float) $r->revenue, 2),
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function inventorySnapshot(AnalyticsFilter $filter): array
    {
        $rows = $this->inventoryRows($filter);
        $value = round(array_sum(array_column($rows, 'stock_value')), 2);
        $low = count(array_filter($rows, fn ($r) => $r['status'] === 'low'));
        $out = count(array_filter($rows, fn ($r) => $r['status'] === 'out'));
        $in = count(array_filter($rows, fn ($r) => $r['status'] === 'ok'));

        $byValue = collect($rows)->sortByDesc('stock_value')->values();

        return [
            'sku_count' => count($rows),
            'current_stock_units' => (int) array_sum(array_column($rows, 'on_hand')),
            'inventory_value' => $value,
            'low_stock' => $low,
            'out_of_stock' => $out,
            'in_stock' => $in,
            'by_value_desc' => $byValue->take(20)->all(),
            'by_value_asc' => $byValue->sortBy('stock_value')->values()->take(20)->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inventoryRows(AnalyticsFilter $filter): array
    {
        $inventories = VariantInventory::query()
            ->with(['variant.product:id,name,sku,cost_price,price', 'inventoryLocation:id,store_id,code'])
            ->where('is_active', true)
            ->whereHas('inventoryLocation', function ($q) use ($filter) {
                if ($filter->hasStores()) {
                    $q->whereIn('store_id', $filter->storeIds);
                }
            })
            ->get();

        return $inventories->map(function (VariantInventory $inv) {
            $onHand = (int) $inv->on_hand;
            $available = (int) ($inv->available ?? max(0, $onHand - (int) $inv->reserved));
            $reorder = (int) ($inv->reorder_level ?? 0);
            $unitCost = (float) ($inv->variant?->product?->cost_price ?? $inv->variant?->product?->price ?? 0);
            $status = $available <= 0 ? 'out' : (($reorder > 0 && $available <= $reorder) || ($reorder === 0 && $available <= 2) ? 'low' : 'ok');

            return [
                'variant_id' => $inv->product_variant_id,
                'sku' => $inv->variant?->sku,
                'product_name' => $inv->variant?->product?->name ?? $inv->variant?->name,
                'store_id' => $inv->inventoryLocation?->store_id,
                'on_hand' => $onHand,
                'available' => $available,
                'unit_cost' => round($unitCost, 2),
                'stock_value' => round($onHand * $unitCost, 2),
                'status' => $status,
            ];
        })->all();
    }

    /**
     * @return list<array{store_id: string, inventory_value: float}>
     */
    private function inventoryValueByStore(AnalyticsFilter $filter): array
    {
        return collect($this->inventoryRows($filter))
            ->groupBy('store_id')
            ->map(fn ($rows, $storeId) => [
                'store_id' => $storeId,
                'inventory_value' => round((float) $rows->sum('stock_value'), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{fast: list<array<string, mixed>>, slow: list<array<string, mixed>>, dead: list<array<string, mixed>>}
     */
    private function productVelocity(AnalyticsFilter $filter): array
    {
        $sold = collect($this->salesByProduct($filter))->keyBy('product_id');

        $fast = $sold->sortByDesc('units')->values()->take(10)->all();
        $slow = $sold->sortBy('units')->values()->take(10)->all();

        $dead = collect($this->inventoryRows($filter))
            ->filter(function ($row) use ($sold) {
                // Dead = stock on hand but no sales in period (match via product name loosely / sku).
                $hit = $sold->first(fn ($s) => ($s['sku'] ?? null) === ($row['sku'] ?? null));

                return $row['on_hand'] > 0 && $hit === null;
            })
            ->sortByDesc('stock_value')
            ->values()
            ->take(10)
            ->all();

        return compact('fast', 'slow', 'dead');
    }

    /**
     * @return array{open_sessions: int, closed_sessions: int, sessions_in_period: int}
     */
    private function sessionSnapshot(AnalyticsFilter $filter): array
    {
        $base = PosSession::query()
            ->when($filter->hasStores(), fn ($q) => $q->whereIn('store_id', $filter->storeIds))
            ->when($filter->cashierId, fn ($q) => $q->where('admin_id', $filter->cashierId));

        return [
            'open_sessions' => (clone $base)->where('status', PosSessionStatus::Open)->count(),
            'closed_sessions' => (clone $base)
                ->where('status', PosSessionStatus::Closed)
                ->whereBetween('closed_at', [$filter->period->from, $filter->period->to])
                ->count(),
            'sessions_in_period' => (clone $base)
                ->where(function ($q) use ($filter) {
                    $q->whereBetween('opened_at', [$filter->period->from, $filter->period->to])
                        ->orWhereBetween('closed_at', [$filter->period->from, $filter->period->to]);
                })
                ->count(),
        ];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return array{type: string, columns: list<string>, rows: list<array<string, mixed>>, period: array<string, string>}
     */
    private function tableFromRows(string $type, array $columns, array $rows, AnalyticsFilter $filter): array
    {
        return [
            'type' => 'analytics-'.$type,
            'columns' => $columns,
            'rows' => $rows,
            'period' => $this->periodPayload($filter),
        ];
    }

    /**
     * @param  array<string, mixed>  $assoc
     * @return array{type: string, columns: list<string>, rows: list<array<string, mixed>>, period: array<string, string>}
     */
    private function tableFromAssoc(string $type, array $assoc, AnalyticsFilter $filter): array
    {
        return [
            'type' => 'analytics-'.$type,
            'columns' => ['metric', 'value'],
            'rows' => collect($assoc)->map(fn ($v, $k) => ['metric' => $k, 'value' => $v])->values()->all(),
            'period' => $this->periodPayload($filter),
        ];
    }

    /**
     * @param  array<string, float|int>  $map
     * @return array{type: string, columns: list<string>, rows: list<array<string, mixed>>, period: array<string, string>}
     */
    private function tableFromMap(string $type, array $map, AnalyticsFilter $filter): array
    {
        return [
            'type' => 'analytics-'.$type,
            'columns' => ['method', 'amount'],
            'rows' => collect($map)->map(fn ($v, $k) => ['method' => $k, 'amount' => $v])->values()->all(),
            'period' => $this->periodPayload($filter),
        ];
    }
}
