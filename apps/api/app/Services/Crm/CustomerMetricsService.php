<?php

namespace App\Services\Crm;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundTransactionStatus;
use App\Events\Crm\CustomerMetricsUpdated;
use App\Models\CustomerMetric;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\ProfitRecord;
use App\Models\Refund;
use App\Models\RefundTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stored CRM metrics projection from existing commerce records (orders, payments, refunds, profit).
 */
class CustomerMetricsService
{
    public function ensure(CustomerProfile $profile): CustomerMetric
    {
        return CustomerMetric::query()->firstOrCreate(
            ['customer_profile_id' => $profile->id],
            [
                'currency' => 'TZS',
                'calculated_at' => null,
            ],
        );
    }

    public function recalculate(CustomerProfile $profile, bool $dispatchEvent = true): CustomerMetric
    {
        return DB::transaction(function () use ($profile, $dispatchEvent) {
            $metric = $this->ensure($profile);
            $userId = $profile->user_id;

            $orders = Order::query()
                ->real()
                ->where('user_id', $userId)
                ->get(['id', 'status', 'total', 'currency', 'placed_at', 'created_at', 'paid_at']);

            $totalOrders = $orders->count();
            $completedOrders = $orders->whereIn('status', [
                OrderStatus::Completed,
                OrderStatus::Delivered,
            ])->count();
            $cancelledOrders = $orders->where('status', OrderStatus::Cancelled)->count();

            $spendOrders = $orders->filter(function (Order $order) {
                return in_array($order->status, [
                    OrderStatus::Paid,
                    OrderStatus::Confirmed,
                    OrderStatus::Processing,
                    OrderStatus::Shipped,
                    OrderStatus::Delivered,
                    OrderStatus::Completed,
                    OrderStatus::Refunded,
                ], true);
            });

            $totalSpend = round((float) $spendOrders->sum(fn (Order $o) => (float) $o->total), 2);
            $aov = $spendOrders->count() > 0
                ? round($totalSpend / $spendOrders->count(), 2)
                : 0.0;

            $legacyRefunds = (float) Refund::query()
                ->where('user_id', $userId)
                ->whereIn('status', ['completed', 'refunded', 'paid'])
                ->sum('amount');

            $engineRefunds = (float) RefundTransaction::query()
                ->whereHas('order', fn ($q) => $q->where('user_id', $userId)->where('is_demo', false))
                ->where('status', RefundTransactionStatus::Completed)
                ->sum('amount');

            $totalRefunds = round($legacyRefunds + $engineRefunds, 2);

            $orderIds = $orders->pluck('id');
            $grossProfit = round((float) ProfitRecord::query()
                ->whereIn('order_id', $orderIds)
                ->sum('gross_profit'), 2);

            $firstOrderAt = $orders->min(fn (Order $o) => $o->placed_at ?? $o->created_at);
            $lastOrderAt = $orders->max(fn (Order $o) => $o->placed_at ?? $o->created_at);

            $lastPaymentLegacy = Payment::query()
                ->where('user_id', $userId)
                ->where('status', PaymentStatus::Paid)
                ->max('paid_at');

            $lastPaymentTx = PaymentTransaction::query()
                ->whereHas('order', fn ($q) => $q->where('user_id', $userId)->where('is_demo', false))
                ->where('status', PaymentTransactionStatus::Successful)
                ->max('completed_at');

            $lastPaymentAt = collect([$lastPaymentLegacy, $lastPaymentTx, $orders->max('paid_at')])
                ->filter()
                ->max();

            $currency = (string) ($orders->sortByDesc(fn (Order $o) => $o->placed_at ?? $o->created_at)->first()?->currency ?: 'TZS');

            $metric->fill([
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'cancelled_orders' => $cancelledOrders,
                'total_spend' => number_format($totalSpend, 2, '.', ''),
                'total_refunds' => number_format($totalRefunds, 2, '.', ''),
                'gross_profit_generated' => number_format($grossProfit, 2, '.', ''),
                'average_order_value' => number_format($aov, 2, '.', ''),
                'first_order_at' => $firstOrderAt,
                'last_order_at' => $lastOrderAt,
                'last_payment_at' => $lastPaymentAt,
                'last_activity_at' => collect([$lastOrderAt, $lastPaymentAt, now()])->filter()->max(),
                'currency' => strtoupper($currency),
                'calculated_at' => now(),
            ]);
            $metric->save();

            if ($dispatchEvent) {
                try {
                    event(new CustomerMetricsUpdated($metric->fresh() ?? $metric));
                } catch (\Throwable $e) {
                    Log::warning('crm.metrics_updated_event_failed', [
                        'profile_id' => $profile->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            return $metric->fresh() ?? $metric;
        });
    }

    public function recalculateForUserId(?string $userId): ?CustomerMetric
    {
        if ($userId === null) {
            return null;
        }

        $profile = CustomerProfile::query()->where('user_id', $userId)->first();
        if ($profile === null) {
            return null;
        }

        return $this->recalculate($profile);
    }
}
