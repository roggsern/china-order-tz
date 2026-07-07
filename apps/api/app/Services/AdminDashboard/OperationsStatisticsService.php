<?php

namespace App\Services\AdminDashboard;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Shipments\OrderShipmentStatusResolver;

class OperationsStatisticsService
{
    public function __construct(
        private readonly OrderShipmentStatusResolver $statusResolver,
    ) {}

    /**
     * @return array{
     *     total_orders: int,
     *     pending_payments: int,
     *     total_customers: int,
     *     total_products: int
     * }
     */
    public function summary(): array
    {
        return [
            'total_orders' => Order::query()->count(),
            'pending_payments' => Payment::query()->where('status', PaymentStatus::Pending)->count(),
            'total_customers' => User::query()->count(),
            'total_products' => Product::query()->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function shipmentOverview(): array
    {
        $expression = $this->statusResolver->effectiveStatusSqlExpression();

        $counts = Order::query()
            ->selectRaw("{$expression} as effective_status, COUNT(*) as total")
            ->groupBy('effective_status')
            ->pluck('total', 'effective_status');

        $overview = [];

        foreach (ShipmentStatus::timeline() as $status) {
            $overview[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $overview;
    }

    /**
     * @return list<array{type: string, message: string, count: int}>
     */
    public function alerts(): array
    {
        $alerts = [];
        $expression = $this->statusResolver->effectiveStatusSqlExpression();
        $staleThreshold = now()->subDays(3);

        $supplierProcessingDelayed = Order::query()
            ->whereRaw("({$expression}) = ?", [ShipmentStatus::SupplierProcessing->value])
            ->whereRaw('COALESCE(shipment_status_updated_at, updated_at) <= ?', [$staleThreshold])
            ->count();

        if ($supplierProcessingDelayed > 0) {
            $alerts[] = [
                'type' => 'supplier_processing_delayed',
                'message' => 'Orders waiting for supplier processing for more than 3 days.',
                'count' => $supplierProcessingDelayed,
            ];
        }

        $chinaWarehouseDelayed = Order::query()
            ->whereRaw("({$expression}) = ?", [ShipmentStatus::ArrivedChinaWarehouse->value])
            ->whereRaw('COALESCE(shipment_status_updated_at, updated_at) <= ?', [$staleThreshold])
            ->count();

        if ($chinaWarehouseDelayed > 0) {
            $alerts[] = [
                'type' => 'china_warehouse_delayed',
                'message' => 'Orders staying in the China warehouse for more than 3 days.',
                'count' => $chinaWarehouseDelayed,
            ];
        }

        $pendingPayments = Payment::query()->where('status', PaymentStatus::Pending)->count();

        if ($pendingPayments > 0) {
            $alerts[] = [
                'type' => 'pending_payments',
                'message' => 'Payments are still pending confirmation.',
                'count' => $pendingPayments,
            ];
        }

        $cancelledToday = Order::query()
            ->where('status', OrderStatus::Cancelled)
            ->whereDate('cancelled_at', today())
            ->count();

        if ($cancelledToday > 0) {
            $alerts[] = [
                'type' => 'cancelled_orders_today',
                'message' => 'Orders were cancelled today.',
                'count' => $cancelledToday,
            ];
        }

        return $alerts;
    }
}
