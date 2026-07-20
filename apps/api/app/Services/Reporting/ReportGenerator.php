<?php

namespace App\Services\Reporting;

use App\Enums\PaymentTransactionStatus;
use App\Models\Notification;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\RefundTransaction;
use App\Models\ReturnRequest;
use App\Models\Shipment;
use App\Models\WarehouseJob;
use App\Services\Reporting\DTOs\ReportPeriod;
use InvalidArgumentException;

/**
 * Builds tabular report datasets for admin reports and exports.
 * Read-only — never mutates business data.
 */
class ReportGenerator
{
    public const TYPES = [
        'sales',
        'orders',
        'payments',
        'warehouse',
        'shipments',
        'returns',
        'notifications',
        'pos_sessions',
    ];

    public function __construct(
        private readonly MetricsEngine $metrics,
    ) {}

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return array{type: string, period: array{from: string, to: string}, summary: array<string, mixed>, rows: list<array<string, mixed>>, columns: list<string>}
     */
    public function generate(string $type, array $filters = []): array
    {
        $type = strtolower($type);
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown report type [{$type}].");
        }

        $period = ReportPeriod::fromInput($filters['from'] ?? null, $filters['to'] ?? null);

        return match ($type) {
            'sales' => $this->salesReport($period),
            'orders' => $this->ordersReport($period),
            'payments' => $this->paymentsReport($period),
            'warehouse' => $this->warehouseReport($period),
            'shipments' => $this->shipmentsReport($period),
            'returns' => $this->returnsReport($period),
            'notifications' => $this->notificationsReport($period),
            'pos_sessions' => $this->posSessionsReport($period),
        };
    }

    private function posSessionsReport(ReportPeriod $period): array
    {
        $sessions = \App\Models\PosSession::query()
            ->with(['store', 'admin', 'terminal'])
            ->whereBetween('opened_at', [$period->from, $period->to])
            ->latest('opened_at')
            ->limit(500)
            ->get();

        $totalSales = '0.00';
        $varianceAbs = '0.00';
        foreach ($sessions as $session) {
            $breakdown = is_array($session->payment_breakdown) ? $session->payment_breakdown : [];
            foreach ($breakdown as $row) {
                $totalSales = bcadd($totalSales, number_format((float) ($row['amount'] ?? 0), 2, '.', ''), 2);
            }
            if ($session->variance_amount !== null) {
                $varianceAbs = bcadd(
                    $varianceAbs,
                    number_format(abs((float) $session->variance_amount), 2, '.', ''),
                    2,
                );
            }
        }

        $rows = $sessions->map(fn (\App\Models\PosSession $session) => [
            'session_id' => $session->id,
            'store' => $session->store?->name,
            'cashier' => $session->admin?->name,
            'terminal' => $session->terminal?->code,
            'status' => $session->status instanceof \BackedEnum ? $session->status->value : $session->status,
            'opening_float' => (string) $session->opening_float,
            'expected_cash' => (string) ($session->expected_cash ?? ''),
            'closing_cash' => (string) ($session->closing_cash ?? ''),
            'variance_amount' => (string) ($session->variance_amount ?? ''),
            'variance_type' => $session->variance_type instanceof \BackedEnum
                ? $session->variance_type->value
                : (string) ($session->variance_type ?? ''),
            'transaction_count' => (int) $session->transaction_count,
            'opened_at' => optional($session->opened_at)?->toDateTimeString(),
            'closed_at' => optional($session->closed_at)?->toDateTimeString(),
        ])->all();

        return $this->wrap('pos_sessions', $period, [
            'sessions' => $sessions->count(),
            'open' => $sessions->where('status', \App\Enums\PosSessionStatus::Open)->count(),
            'closed' => $sessions->where('status', \App\Enums\PosSessionStatus::Closed)->count(),
            'total_sales_snapshot' => $totalSales,
            'absolute_variance' => $varianceAbs,
        ], $rows, [
            'session_id', 'store', 'cashier', 'terminal', 'status',
            'opening_float', 'expected_cash', 'closing_cash',
            'variance_amount', 'variance_type', 'transaction_count',
            'opened_at', 'closed_at',
        ]);
    }

    private function salesReport(ReportPeriod $period): array
    {
        $summary = $this->metrics->sales($period);
        $rows = Order::query()
            ->real()
            ->with('user')
            ->whereBetween('created_at', [$period->from, $period->to])
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn (Order $order) => [
                'order_number' => $order->order_number,
                'customer' => $order->user?->email,
                'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
                'total' => (string) $order->total,
                'currency' => $order->currency,
                'created_at' => optional($order->created_at)?->toDateTimeString(),
            ])
            ->all();

        return $this->wrap('sales', $period, $summary, $rows, [
            'order_number', 'customer', 'status', 'total', 'currency', 'created_at',
        ]);
    }

    private function ordersReport(ReportPeriod $period): array
    {
        $summary = $this->metrics->orders($period);
        $rows = Order::query()
            ->real()
            ->with('user')
            ->whereBetween('created_at', [$period->from, $period->to])
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn (Order $order) => [
                'order_number' => $order->order_number,
                'customer' => $order->user?->name,
                'email' => $order->user?->email,
                'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
                'total' => (string) $order->total,
                'placed_at' => optional($order->placed_at ?? $order->created_at)?->toDateTimeString(),
            ])
            ->all();

        return $this->wrap('orders', $period, $summary, $rows, [
            'order_number', 'customer', 'email', 'status', 'total', 'placed_at',
        ]);
    }

    private function paymentsReport(ReportPeriod $period): array
    {
        $summary = [
            'successful' => PaymentTransaction::query()
                ->where('status', PaymentTransactionStatus::Successful->value)
                ->whereBetween('created_at', [$period->from, $period->to])
                ->count(),
            'pending' => PaymentTransaction::query()
                ->whereIn('status', [
                    PaymentTransactionStatus::Pending->value,
                    PaymentTransactionStatus::Processing->value,
                ])
                ->whereBetween('created_at', [$period->from, $period->to])
                ->count(),
            'failed' => PaymentTransaction::query()
                ->where('status', PaymentTransactionStatus::Failed->value)
                ->whereBetween('created_at', [$period->from, $period->to])
                ->count(),
            'total_amount' => (float) PaymentTransaction::query()
                ->where('status', PaymentTransactionStatus::Successful->value)
                ->whereBetween('created_at', [$period->from, $period->to])
                ->sum('amount'),
        ];

        $rows = PaymentTransaction::query()
            ->with('order')
            ->whereBetween('created_at', [$period->from, $period->to])
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn (PaymentTransaction $tx) => [
                'id' => $tx->id,
                'order_number' => $tx->order?->order_number,
                'amount' => (string) $tx->amount,
                'currency' => $tx->currency ?? 'TZS',
                'status' => $tx->status instanceof \BackedEnum ? $tx->status->value : $tx->status,
                'provider' => $tx->provider instanceof \BackedEnum
                    ? $tx->provider->value
                    : $tx->provider,
                'created_at' => optional($tx->created_at)?->toDateTimeString(),
            ])
            ->all();

        return $this->wrap('payments', $period, $summary, $rows, [
            'id', 'order_number', 'amount', 'currency', 'status', 'provider', 'created_at',
        ]);
    }

    private function warehouseReport(ReportPeriod $period): array
    {
        $summary = $this->metrics->warehouse($period);
        $rows = WarehouseJob::query()
            ->with('order')
            ->whereBetween('created_at', [$period->from, $period->to])
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn (WarehouseJob $job) => [
                'job_number' => $job->job_number,
                'order_number' => $job->order?->order_number,
                'status' => $job->status instanceof \BackedEnum ? $job->status->value : $job->status,
                'picked_at' => optional($job->picked_at)?->toDateTimeString(),
                'packed_at' => optional($job->packed_at)?->toDateTimeString(),
                'ready_at' => optional($job->ready_at)?->toDateTimeString(),
            ])
            ->all();

        return $this->wrap('warehouse', $period, $summary, $rows, [
            'job_number', 'order_number', 'status', 'picked_at', 'packed_at', 'ready_at',
        ]);
    }

    private function shipmentsReport(ReportPeriod $period): array
    {
        $summary = $this->metrics->shipments($period);
        $rows = Shipment::query()
            ->with('order')
            ->whereBetween('created_at', [$period->from, $period->to])
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn (Shipment $shipment) => [
                'shipment_number' => $shipment->shipment_number,
                'order_number' => $shipment->order?->order_number,
                'status' => $shipment->status instanceof \BackedEnum ? $shipment->status->value : $shipment->status,
                'transport_mode' => $shipment->transport_mode instanceof \BackedEnum
                    ? $shipment->transport_mode->value
                    : $shipment->transport_mode,
                'delivered_at' => optional($shipment->delivered_at)?->toDateTimeString(),
                'created_at' => optional($shipment->created_at)?->toDateTimeString(),
            ])
            ->all();

        return $this->wrap('shipments', $period, $summary, $rows, [
            'shipment_number', 'order_number', 'status', 'transport_mode', 'delivered_at', 'created_at',
        ]);
    }

    private function returnsReport(ReportPeriod $period): array
    {
        $summary = $this->metrics->returns($period);
        $rows = ReturnRequest::query()
            ->with(['order', 'customer'])
            ->whereBetween('created_at', [$period->from, $period->to])
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn (ReturnRequest $return) => [
                'id' => $return->id,
                'order_number' => $return->order?->order_number,
                'customer' => $return->customer?->email,
                'status' => $return->status instanceof \BackedEnum ? $return->status->value : $return->status,
                'reason' => $return->reason,
                'created_at' => optional($return->created_at)?->toDateTimeString(),
            ])
            ->all();

        return $this->wrap('returns', $period, $summary, $rows, [
            'id', 'order_number', 'customer', 'status', 'reason', 'created_at',
        ]);
    }

    private function notificationsReport(ReportPeriod $period): array
    {
        $summary = $this->metrics->notifications($period);
        $rows = Notification::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn (Notification $n) => [
                'id' => $n->id,
                'event_type' => $n->event_type,
                'channel' => $n->channel instanceof \BackedEnum ? $n->channel->value : $n->channel,
                'status' => $n->status instanceof \BackedEnum ? $n->status->value : $n->status,
                'provider' => $n->provider,
                'title' => $n->title,
                'created_at' => optional($n->created_at)?->toDateTimeString(),
            ])
            ->all();

        return $this->wrap('notifications', $period, $summary, $rows, [
            'id', 'event_type', 'channel', 'status', 'provider', 'title', 'created_at',
        ]);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     * @return array{type: string, period: array{from: string, to: string}, summary: array<string, mixed>, rows: list<array<string, mixed>>, columns: list<string>}
     */
    private function wrap(string $type, ReportPeriod $period, array $summary, array $rows, array $columns): array
    {
        return [
            'type' => $type,
            'period' => [
                'from' => $period->from->toDateString(),
                'to' => $period->to->toDateString(),
            ],
            'summary' => $summary,
            'rows' => $rows,
            'columns' => $columns,
        ];
    }
}
