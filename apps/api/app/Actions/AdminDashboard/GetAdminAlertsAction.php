<?php

namespace App\Actions\AdminDashboard;

use App\Services\Reporting\AdminAlertService;

/**
 * Thin adapter — alert data comes only from AdminAlertService (Reporting Engine stack).
 */
class GetAdminAlertsAction
{
    public function __construct(
        private readonly AdminAlertService $alerts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(?string $from = null, ?string $to = null): array
    {
        return $this->alerts->alerts($from, $to);
    }
}
