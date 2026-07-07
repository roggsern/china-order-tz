<?php

namespace App\Actions\AdminDashboard;

use App\Services\AdminDashboard\OperationsStatisticsService;

class ShowOperationsDashboardAction
{
    public function __construct(
        private readonly OperationsStatisticsService $statisticsService,
    ) {}

    /**
     * @return array{
     *     summary: array{
     *         total_orders: int,
     *         pending_payments: int,
     *         total_customers: int,
     *         total_products: int
     *     },
     *     shipments: array<string, int>,
     *     alerts: list<array{type: string, message: string, count: int}>
     * }
     */
    public function handle(): array
    {
        return [
            'summary' => $this->statisticsService->summary(),
            'shipments' => $this->statisticsService->shipmentOverview(),
            'alerts' => $this->statisticsService->alerts(),
        ];
    }
}
