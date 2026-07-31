<?php

namespace App\Services\Reporting;

use App\Enums\ChinaQcStatus;
use App\Enums\ChinaWorkflowStage;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\OrderStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\WarehouseJobStatus;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Store;
use App\Services\Reporting\DTOs\ReportPeriod;
use Illuminate\Database\Eloquent\Builder;

/**
 * Command center dashboard aggregation.
 *
 * Reuses MetricsEngine for business metrics and snapshot operational counts.
 * Fulfillment pipeline / attention queries scan active fulfillments only.
 *
 * Expensive calculations (documented):
 * - attention_items: up to 5 conditional counts on fulfillments + joins
 * - china_pipeline / tz_local: multiple scoped counts on active fulfillments
 * - store_summary: grouped count on today's orders
 */
class CommandCenterDashboardService
{
    private const STUCK_FULFILLMENT_DAYS = 7;

    public function __construct(
        private readonly MetricsEngine $metrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(ReportPeriod $period): array
    {
        $ordersMetrics = $this->metrics->orders($period);
        $customersMetrics = $this->metrics->customers($period);
        $warehouseMetrics = $this->metrics->warehouse(null);
        $shipmentMetrics = $this->metrics->shipments(null);
        $returnsMetrics = $this->metrics->returns($period);

        $attention = $this->attentionItems($returnsMetrics['open']);

        return [
            'overview' => $this->overview($ordersMetrics, $customersMetrics, $attention),
            'operations' => $this->operations($warehouseMetrics, $shipmentMetrics, $returnsMetrics),
            'china_pipeline' => $this->chinaPipeline(),
            'tz_local' => $this->tzLocalPipeline(),
            'attention_items' => $attention,
            'store_summary' => $this->storeSummary(),
        ];
    }

    /**
     * @param  array<string, int|float>  $ordersMetrics
     * @param  array<string, int|float>  $customersMetrics
     * @param  list<array<string, mixed>>  $attention
     * @return array<string, int|float>
     */
    private function overview(array $ordersMetrics, array $customersMetrics, array $attention): array
    {
        $today = [now()->startOfDay(), now()->endOfDay()];
        $paidStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Confirmed->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
            OrderStatus::Completed->value,
        ];

        $todayOrders = Order::query()->real()->whereBetween('created_at', $today);
        $paidToday = (clone $todayOrders)->whereIn('status', $paidStatuses)->count();
        $revenueToday = (float) (clone $todayOrders)->whereIn('status', $paidStatuses)->sum('total');

        return [
            'orders_today' => (int) ($ordersMetrics['orders_today'] ?? 0),
            'revenue_today' => round($revenueToday, 2),
            'paid_orders_today' => $paidToday,
            'pending_actions' => (int) collect($attention)->sum('count'),
            'customers_total' => (int) ($customersMetrics['total_customers'] ?? 0),
            'new_customers' => (int) ($customersMetrics['new_customers'] ?? 0),
        ];
    }

    /**
     * @param  array<string, int>  $warehouseMetrics
     * @param  array<string, int>  $shipmentMetrics
     * @param  array<string, int|float>  $returnsMetrics
     * @return array<string, mixed>
     */
    private function operations(array $warehouseMetrics, array $shipmentMetrics, array $returnsMetrics): array
    {
        $active = $this->activeFulfillmentsQuery();

        return [
            'fulfillment_queue' => [
                'total' => (clone $active)->count(),
                'china' => (clone $active)->where('strategy', FulfillmentStrategy::China->value)->count(),
                'local' => (clone $active)->where('strategy', FulfillmentStrategy::Local->value)->count(),
            ],
            'warehouse' => $warehouseMetrics,
            'shipments' => $shipmentMetrics,
            'open_returns' => (int) ($returnsMetrics['open'] ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function chinaPipeline(): array
    {
        $base = $this->chinaActive();

        $procurementStages = [
            ChinaWorkflowStage::AwaitingProcurement->value,
            ChinaWorkflowStage::ProcurementInProgress->value,
            ChinaWorkflowStage::PartiallyReceived->value,
        ];

        $exportReadyStages = [
            ChinaWorkflowStage::ExportReady->value,
            ChinaWorkflowStage::AgentHandedOff->value,
            ChinaWorkflowStage::CompanyShippingReady->value,
        ];

        $warehouseStatuses = [
            WarehouseJobStatus::Picking->value,
            WarehouseJobStatus::Picked->value,
            WarehouseJobStatus::Packing->value,
            WarehouseJobStatus::Packed->value,
        ];

        $pendingShipmentStatuses = [
            ShipmentLifecycleStatus::Pending->value,
            ShipmentLifecycleStatus::Booked->value,
        ];

        return [
            'procurement' => (clone $base)
                ->where(function (Builder $query) use ($procurementStages): void {
                    $query->whereHas('chinaWorkflowRecord', fn (Builder $record) => $record->whereIn('stage', $procurementStages))
                        ->orWhere(function (Builder $pending) {
                            $pending->where('status', FulfillmentStatus::Pending->value)
                                ->whereDoesntHave('chinaWorkflowRecord');
                        });
                })
                ->count(),
            'qc_pending' => (clone $base)
                ->whereHas('chinaWorkflowRecord', function (Builder $record): void {
                    $record->where('stage', ChinaWorkflowStage::QcPending->value)
                        ->orWhereIn('qc_status', [
                            ChinaQcStatus::Pending->value,
                            ChinaQcStatus::Reinspection->value,
                            ChinaQcStatus::Hold->value,
                        ]);
                })
                ->count(),
            'warehouse_packing' => (clone $base)
                ->whereHas('warehouseJob', fn (Builder $job) => $job->whereIn('status', $warehouseStatuses))
                ->count(),
            'export_ready' => (clone $base)
                ->whereHas('chinaWorkflowRecord', function (Builder $record) use ($exportReadyStages): void {
                    $record->whereNotNull('export_ready_at')
                        ->orWhereIn('stage', $exportReadyStages);
                })
                ->count(),
            'shipment_pending' => (clone $base)
                ->where('status', FulfillmentStatus::ReadyForShipping->value)
                ->where(function (Builder $query) use ($pendingShipmentStatuses): void {
                    $query->whereDoesntHave('shipment')
                        ->orWhereHas('shipment', fn (Builder $shipment) => $shipment->whereIn('status', $pendingShipmentStatuses));
                })
                ->count(),
            'arrived_tanzania' => (clone $base)
                ->whereHas('shipment', function (Builder $shipment): void {
                    $shipment->whereNotNull('arrived_at')
                        ->orWhere('status', ShipmentLifecycleStatus::Arrived->value);
                })
                ->count(),
            'awaiting_receiving_choice' => $this->companyShippingAwaitingChoiceQuery($base)->count(),
            'handover_pending' => $this->companyShippingHandoverPendingQuery($base)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function tzLocalPipeline(): array
    {
        $base = $this->localActive();

        return [
            'pending' => (clone $base)->where('status', FulfillmentStatus::Pending->value)->count(),
            'processing' => (clone $base)->where('status', FulfillmentStatus::Processing->value)->count(),
            'ready_for_shipping' => (clone $base)->where('status', FulfillmentStatus::ReadyForShipping->value)->count(),
            'shipped' => (clone $base)->where('status', FulfillmentStatus::Shipped->value)->count(),
            'ready_for_completion' => (clone $base)->where('status', FulfillmentStatus::ReadyForShipping->value)->count(),
        ];
    }

    /**
     * @return list<array{key: string, label: string, count: int, severity: string, href: string}>
     */
    private function attentionItems(int $openReturns): array
    {
        $base = $this->activeFulfillmentsQuery();
        $stuckThreshold = now()->subDays(self::STUCK_FULFILLMENT_DAYS);

        $stuck = (clone $base)->where('updated_at', '<', $stuckThreshold)->count();
        $qcPending = (clone $this->chinaActive())
            ->whereHas('chinaWorkflowRecord', function (Builder $record): void {
                $record->where('stage', ChinaWorkflowStage::QcPending->value)
                    ->orWhereIn('qc_status', [
                        ChinaQcStatus::Pending->value,
                        ChinaQcStatus::Reinspection->value,
                        ChinaQcStatus::Hold->value,
                    ]);
            })
            ->count();
        $awaitingChoice = $this->companyShippingAwaitingChoiceQuery($this->chinaActive())->count();
        $handoverPending = $this->companyShippingHandoverPendingQuery($this->chinaActive())->count();

        return [
            [
                'key' => 'stuck_fulfillment',
                'label' => 'Stuck fulfilment',
                'count' => $stuck,
                'severity' => $stuck > 0 ? 'high' : 'normal',
                'href' => '/admin/fulfillments',
            ],
            [
                'key' => 'pending_qc',
                'label' => 'Pending QC',
                'count' => $qcPending,
                'severity' => $qcPending > 0 ? 'high' : 'normal',
                'href' => '/admin/fulfillments?source=china',
            ],
            [
                'key' => 'awaiting_receiving_choice',
                'label' => 'Arrived TZ — awaiting customer choice',
                'count' => $awaitingChoice,
                'severity' => $awaitingChoice > 0 ? 'high' : 'normal',
                'href' => '/admin/fulfillments?source=china',
            ],
            [
                'key' => 'pending_handover',
                'label' => 'Pending customer handover',
                'count' => $handoverPending,
                'severity' => $handoverPending > 0 ? 'medium' : 'normal',
                'href' => '/admin/fulfillments?source=china',
            ],
            [
                'key' => 'open_returns',
                'label' => 'Open returns',
                'count' => $openReturns,
                'severity' => $openReturns > 0 ? 'medium' : 'normal',
                'href' => '/admin/returns',
            ],
        ];
    }

    /**
     * @return array{active_stores: int, orders_today_by_store: list<array{store_id: string|null, store_name: string|null, orders_today: int}>}
     */
    private function storeSummary(): array
    {
        $today = [now()->startOfDay(), now()->endOfDay()];

        $rows = Order::query()
            ->real()
            ->whereBetween('created_at', $today)
            ->selectRaw('store_id, COUNT(*) as orders_today')
            ->groupBy('store_id')
            ->orderByDesc('orders_today')
            ->get();

        $storeNames = Store::query()
            ->whereIn('id', $rows->pluck('store_id')->filter()->unique())
            ->pluck('name', 'id');

        return [
            'active_stores' => Store::query()->where('is_active', true)->count(),
            'orders_today_by_store' => $rows->map(fn ($row) => [
                'store_id' => $row->store_id,
                'store_name' => $row->store_id !== null
                    ? ($storeNames[$row->store_id] ?? 'Unknown store')
                    : 'Direct / no store',
                'orders_today' => (int) $row->orders_today,
            ])->values()->all(),
        ];
    }

    private function activeFulfillmentsQuery(): Builder
    {
        return Fulfillment::query()->whereNotIn('status', [
            FulfillmentStatus::Delivered->value,
            FulfillmentStatus::Cancelled->value,
        ]);
    }

    private function chinaActive(): Builder
    {
        return $this->activeFulfillmentsQuery()
            ->where('strategy', FulfillmentStrategy::China->value);
    }

    private function localActive(): Builder
    {
        return $this->activeFulfillmentsQuery()
            ->where('strategy', FulfillmentStrategy::Local->value);
    }

    private function companyShippingAwaitingChoiceQuery(Builder $base): Builder
    {
        return (clone $base)
            ->where('status', FulfillmentStatus::Shipped->value)
            ->whereHas('order.deliveryOption', fn (Builder $delivery) => $delivery
                ->where('delivery_type', DeliveryType::CompanyShipping->value)
                ->whereNull('last_mile_receiving_method'))
            ->whereHas('shipment', fn (Builder $shipment) => $shipment
                ->whereNotNull('arrived_at')
                ->orWhere('status', ShipmentLifecycleStatus::Arrived->value));
    }

    private function companyShippingHandoverPendingQuery(Builder $base): Builder
    {
        return (clone $base)
            ->where('status', FulfillmentStatus::Shipped->value)
            ->whereHas('order.deliveryOption', fn (Builder $delivery) => $delivery
                ->where('delivery_type', DeliveryType::CompanyShipping->value)
                ->whereNotNull('last_mile_receiving_method'))
            ->whereHas('shipment', fn (Builder $shipment) => $shipment->whereNotNull('arrived_at'));
    }
}
