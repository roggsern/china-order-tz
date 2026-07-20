<?php

namespace App\Actions\AdminDashboard;

use App\Services\Reporting\ReportingEngine;

/**
 * Thin adapter — dashboard data comes only from the Reporting Engine.
 */
class GetAdminDashboardAction
{
    public function __construct(
        private readonly ReportingEngine $reporting,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(?string $from = null, ?string $to = null): array
    {
        return $this->reporting->dashboard([
            'from' => $from,
            'to' => $to,
        ]);
    }
}
