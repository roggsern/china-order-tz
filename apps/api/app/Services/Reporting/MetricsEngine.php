<?php

namespace App\Services\Reporting;

use App\Enums\NotificationDeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundTransactionStatus;
use App\Enums\ReturnRequestStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\WarehouseJobStatus;
use App\Enums\CustomerLifecycleStatus;
use App\Models\ActivityLog;
use App\Models\CustomerMetric;
use App\Models\CustomerProfile;
use App\Models\OrderDiscountSnapshot;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\RefundTransaction;
use App\Models\ReturnRequest;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\Reporting\DTOs\ReportPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reusable read-only metric calculations.
 * Never mutates business data.
 */
class MetricsEngine
{
    /**
     * @return array{
     *     total_revenue: float,
     *     paid_revenue: float,
     *     pending_revenue: float,
     *     refunded_revenue: float
     * }
     */
    public function sales(?ReportPeriod $period = null): array
    {
        $orders = Order::query()->real();
        $this->applyOrderCreatedAt($orders, $period);

        $paidStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Confirmed->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
            OrderStatus::Completed->value,
        ];

        $paidRevenue = (float) (clone $orders)->whereIn('status', $paidStatuses)->sum('total');
        $pendingRevenue = (float) (clone $orders)->whereIn('status', [
            OrderStatus::Pending->value,
            OrderStatus::PendingPayment->value,
        ])->sum('total');

        $refunds = RefundTransaction::query()
            ->where('status', RefundTransactionStatus::Completed->value);
        if ($period !== null) {
            $refunds->whereBetween('created_at', [$period->from, $period->to]);
        }
        $refundedRevenue = (float) $refunds->sum('amount');

        // Prefer successful payment transactions + legacy paid payments when present.
        $txPaid = PaymentTransaction::query()
            ->where('status', PaymentTransactionStatus::Successful->value);
        if ($period !== null) {
            $txPaid->whereBetween('created_at', [$period->from, $period->to]);
        }
        $txSum = (float) $txPaid->sum('amount');

        $legacyPaid = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereHas('order', fn ($q) => $q->where('is_demo', false));
        if ($period !== null) {
            $legacyPaid->whereBetween('created_at', [$period->from, $period->to]);
        }
        $legacySum = (float) $legacyPaid->sum('amount');

        $paidFromPayments = $txSum > 0 ? $txSum : $legacySum;
        $paidRevenue = max($paidRevenue, $paidFromPayments);

        return [
            'total_revenue' => round($paidRevenue + $pendingRevenue, 2),
            'paid_revenue' => round($paidRevenue, 2),
            'pending_revenue' => round($pendingRevenue, 2),
            'refunded_revenue' => round($refundedRevenue, 2),
        ];
    }

    /**
     * @return array{
     *     orders_today: int,
     *     orders_this_week: int,
     *     orders_this_month: int,
     *     total_orders: int,
     *     completed_orders: int,
     *     cancelled_orders: int
     * }
     */
    public function orders(?ReportPeriod $period = null): array
    {
        $base = Order::query()->real();

        return [
            'orders_today' => (clone $base)->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'orders_this_week' => (clone $base)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'orders_this_month' => (clone $base)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'total_orders' => $this->countOrders($period),
            'completed_orders' => $this->countOrders($period, [
                OrderStatus::Delivered->value,
                OrderStatus::Completed->value,
            ]),
            'cancelled_orders' => $this->countOrders($period, [OrderStatus::Cancelled->value]),
        ];
    }

    /**
     * Promotion usage analytics from stored usages / discount snapshots (not live re-pricing).
     *
     * @return array{
     *     promotions_used: int,
     *     total_discount_amount: float,
     *     orders_with_discount: int,
     *     top_promotions: list<array{promotion_id: string|null, promotion_name: string|null, promotion_code: string|null, usage_count: int, discount_amount: float}>
     * }
     */
    public function promotions(?ReportPeriod $period = null): array
    {
        $usages = PromotionUsage::query();
        if ($period !== null) {
            $usages->whereBetween('used_at', [$period->from, $period->to]);
        }

        $totalDiscount = (float) (clone $usages)->sum('discount_amount');
        $usageCount = (clone $usages)->count();
        $ordersWithDiscount = (int) (clone $usages)->distinct('order_id')->count('order_id');

        $top = PromotionUsage::query()
            ->selectRaw('promotion_id, COUNT(*) as usage_count, SUM(discount_amount) as discount_amount')
            ->when($period, fn ($q) => $q->whereBetween('used_at', [$period->from, $period->to]))
            ->groupBy('promotion_id')
            ->orderByDesc('discount_amount')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                $promotion = Promotion::query()->find($row->promotion_id);

                return [
                    'promotion_id' => $row->promotion_id,
                    'promotion_name' => $promotion?->name,
                    'promotion_code' => $promotion?->code,
                    'usage_count' => (int) $row->usage_count,
                    'discount_amount' => (float) $row->discount_amount,
                ];
            })
            ->all();

        return [
            'promotions_used' => $usageCount,
            'total_discount_amount' => $totalDiscount,
            'orders_with_discount' => $ordersWithDiscount,
            'snapshot_discount_total' => (float) OrderDiscountSnapshot::query()
                ->when($period, fn ($q) => $q->whereBetween('created_at', [$period->from, $period->to]))
                ->sum('discount_amount'),
            'top_promotions' => $top,
        ];
    }

    /**
     * @return array{total_customers: int, new_customers: int, returning_customers: int}
     */
    public function customers(?ReportPeriod $period = null): array
    {
        $period ??= ReportPeriod::fromInput(null, null, 30);

        // Prefer CRM projections when available; fall back to user counts.
        if (CustomerProfile::query()->exists()) {
            $profiles = CustomerProfile::query()->forCustomers();
            $total = (clone $profiles)->count();
            $new = (clone $profiles)->whereBetween('created_at', [$period->from, $period->to])->count();
            $returning = (int) CustomerMetric::query()
                ->whereHas('profile', fn ($q) => $q->forCustomers())
                ->where('total_orders', '>', 1)
                ->count();

            return [
                'total_customers' => $total,
                'new_customers' => $new,
                'returning_customers' => $returning,
                'active_customers' => (clone $profiles)->where('lifecycle_status', CustomerLifecycleStatus::Active)->count(),
                'dormant_customers' => (clone $profiles)->where('lifecycle_status', CustomerLifecycleStatus::Dormant)->count(),
                'blocked_customers' => (clone $profiles)->where('lifecycle_status', CustomerLifecycleStatus::Blocked)->count(),
                'total_lifetime_spend' => (float) CustomerMetric::query()
                    ->whereHas('profile', fn ($q) => $q->forCustomers())
                    ->sum('total_spend'),
            ];
        }

        $total = User::query()->whereHas('roles', fn ($q) => $q->where('slug', 'customer'))->count();
        $new = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'customer'))
            ->whereBetween('created_at', [$period->from, $period->to])
            ->count();

        $returning = (int) DB::table('orders')
            ->where('is_demo', false)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$period->from, $period->to])
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        return [
            'total_customers' => $total,
            'new_customers' => $new,
            'returning_customers' => $returning,
        ];
    }

    /**
     * @return array{picking: int, packing: int, ready_to_ship: int}
     */
    public function warehouse(?ReportPeriod $period = null): array
    {
        $q = WarehouseJob::query();
        if ($period !== null) {
            $q->whereBetween('created_at', [$period->from, $period->to]);
        }

        return [
            'picking' => (clone $q)->whereIn('status', [
                WarehouseJobStatus::Picking->value,
                WarehouseJobStatus::Picked->value,
            ])->count(),
            'packing' => (clone $q)->whereIn('status', [
                WarehouseJobStatus::Packing->value,
                WarehouseJobStatus::Packed->value,
            ])->count(),
            'ready_to_ship' => (clone $q)->where('status', WarehouseJobStatus::ReadyToShip->value)->count(),
        ];
    }

    /**
     * @return array{created: int, in_transit: int, delivered: int}
     */
    public function shipments(?ReportPeriod $period = null): array
    {
        $q = Shipment::query();
        if ($period !== null) {
            $q->whereBetween('created_at', [$period->from, $period->to]);
        }

        return [
            'created' => (clone $q)->whereIn('status', [
                ShipmentLifecycleStatus::Pending->value,
                ShipmentLifecycleStatus::Booked->value,
            ])->count(),
            'in_transit' => (clone $q)->whereIn('status', [
                ShipmentLifecycleStatus::InTransit->value,
                ShipmentLifecycleStatus::Arrived->value,
            ])->count(),
            'delivered' => (clone $q)->where('status', ShipmentLifecycleStatus::Delivered->value)->count(),
        ];
    }

    /**
     * @return array{open: int, approved: int, completed: int, refunded_amount: float}
     */
    public function returns(?ReportPeriod $period = null): array
    {
        $q = ReturnRequest::query();
        if ($period !== null) {
            $q->whereBetween('created_at', [$period->from, $period->to]);
        }

        $refunds = RefundTransaction::query()
            ->where('status', RefundTransactionStatus::Completed->value);
        if ($period !== null) {
            $refunds->whereBetween('created_at', [$period->from, $period->to]);
        }

        return [
            'open' => (clone $q)->whereIn('status', [
                ReturnRequestStatus::Requested->value,
                ReturnRequestStatus::Approved->value,
                ReturnRequestStatus::Inspection->value,
            ])->count(),
            'approved' => (clone $q)->where('status', ReturnRequestStatus::Approved->value)->count(),
            'completed' => (clone $q)->where('status', ReturnRequestStatus::Completed->value)->count(),
            'refunded_amount' => round((float) $refunds->sum('amount'), 2),
        ];
    }

    /**
     * @return array{sent: int, failed: int, pending: int}
     */
    public function notifications(?ReportPeriod $period = null): array
    {
        $q = Notification::query();
        if ($period !== null) {
            $q->whereBetween('created_at', [$period->from, $period->to]);
        }

        return [
            'sent' => (clone $q)->where('status', NotificationDeliveryStatus::Sent->value)->count(),
            'failed' => (clone $q)->where('status', NotificationDeliveryStatus::Failed->value)->count(),
            'pending' => (clone $q)->whereIn('status', [
                NotificationDeliveryStatus::Pending->value,
                NotificationDeliveryStatus::Processing->value,
            ])->count(),
        ];
    }

    /**
     * @return list<array{date: string, revenue: float}>
     */
    public function dailyRevenue(ReportPeriod $period): array
    {
        $rows = Order::query()
            ->real()
            ->whereIn('status', [
                OrderStatus::Paid->value,
                OrderStatus::Confirmed->value,
                OrderStatus::Processing->value,
                OrderStatus::Shipped->value,
                OrderStatus::Delivered->value,
                OrderStatus::Completed->value,
            ])
            ->whereBetween('created_at', [$period->from, $period->to])
            ->selectRaw('DATE(created_at) as day, SUM(total) as revenue')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        return $this->fillDailySeries($period, $rows, 'revenue');
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function ordersTrend(ReportPeriod $period): array
    {
        $rows = Order::query()
            ->real()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        return $this->fillDailySeries($period, $rows, 'count', true);
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    public function paymentStatusBreakdown(?ReportPeriod $period = null): array
    {
        $q = PaymentTransaction::query();
        if ($period !== null) {
            $q->whereBetween('created_at', [$period->from, $period->to]);
        }

        return $q->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    public function warehouseStatusBreakdown(?ReportPeriod $period = null): array
    {
        $q = WarehouseJob::query();
        if ($period !== null) {
            $q->whereBetween('created_at', [$period->from, $period->to]);
        }

        return $q->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    public function shipmentStatusBreakdown(?ReportPeriod $period = null): array
    {
        $q = Shipment::query();
        if ($period !== null) {
            $q->whereBetween('created_at', [$period->from, $period->to]);
        }

        return $q->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function returnsTrend(ReportPeriod $period): array
    {
        $rows = ReturnRequest::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        return $this->fillDailySeries($period, $rows, 'count', true);
    }

    /**
     * @return list<array{product_id: string|null, name: string, quantity: int, revenue: float}>
     */
    public function topProducts(int $limit = 5, ?ReportPeriod $period = null): array
    {
        $q = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.is_demo', false)
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at');

        if ($period !== null) {
            $q->whereBetween('orders.created_at', [$period->from, $period->to]);
        }

        return $q->selectRaw('
                order_items.product_id,
                COALESCE(order_items.product_name_snapshot, order_items.product_name, "Unknown") as name,
                SUM(order_items.quantity) as quantity,
                SUM(COALESCE(order_items.line_total, order_items.total_price, 0)) as revenue
            ')
            ->groupBy('order_items.product_id', 'name')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => (string) $row->name,
                'quantity' => (int) $row->quantity,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentActivity(int $limit = 10): array
    {
        return ActivityLog::query()
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'event_type' => $log->event_type instanceof \BackedEnum
                    ? $log->event_type->value
                    : $log->event_type,
                'description' => $log->description,
                'actor_type' => $log->actor_type instanceof \BackedEnum
                    ? $log->actor_type->value
                    : $log->actor_type,
                'created_at' => optional($log->created_at)?->toIso8601String(),
            ])
            ->all();
    }

    /** @param  list<string>|null  $statuses */
    private function countOrders(?ReportPeriod $period, ?array $statuses = null): int
    {
        $q = Order::query()->real();
        $this->applyOrderCreatedAt($q, $period);
        if ($statuses !== null) {
            $q->whereIn('status', $statuses);
        }

        return $q->count();
    }

    private function applyOrderCreatedAt($query, ?ReportPeriod $period): void
    {
        if ($period !== null) {
            $query->whereBetween('created_at', [$period->from, $period->to]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<string, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function fillDailySeries(ReportPeriod $period, $rows, string $valueKey, bool $asInt = false): array
    {
        $series = [];
        $cursor = Carbon::parse($period->from)->startOfDay();
        $end = Carbon::parse($period->to)->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $raw = $rows->get($key);
            $value = $raw?->{$valueKey} ?? 0;
            $metricKey = $valueKey === 'revenue' ? 'revenue' : 'count';
            $series[] = [
                'date' => $key,
                $metricKey => $asInt ? (int) $value : round((float) $value, 2),
            ];
            $cursor->addDay();
        }

        return $series;
    }
}
