<?php

namespace App\Services\Settings;

use App\Services\ConfigurationHealth\ConfigurationHealthService;

/**
 * Read-only Settings Control Center dashboard.
 * Composes configuration health + audit history — does not create storage or mutate engines.
 */
final class ConfigurationDashboardService
{
    public function __construct(
        private readonly ConfigurationHealthService $health,
        private readonly SettingsAuditQueryService $auditHistory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $health = $this->health->report();
        $moduleStatuses = $this->moduleStatuses($health['checks'] ?? []);

        return [
            'health_score' => $health['overall_score'],
            'status' => $health['status'],
            'summary' => $health['summary'],
            'module_statuses' => $moduleStatuses,
            'quick_actions' => $this->quickActions(),
            'recent_changes' => $this->auditHistory->recent(8),
        ];
    }

    /**
     * @param  list<array{group: string, status: string, message: string, severity: string}>  $checks
     * @return list<array<string, mixed>>
     */
    private function moduleStatuses(array $checks): array
    {
        $modules = [
            'payments' => [
                'key' => 'payments',
                'label' => 'Payments',
                'href' => '/admin/settings/payments',
                'permission' => 'payments.config.view',
            ],
            'shipping' => [
                'key' => 'shipping',
                'label' => 'Shipping',
                'href' => '/admin/settings/shipping',
                'permission' => 'shipping.view',
            ],
            'notifications' => [
                'key' => 'notifications',
                'label' => 'Notifications',
                'href' => '/admin/settings/notifications',
                'permission' => 'notifications.view',
            ],
            'store' => [
                'key' => 'store',
                'label' => 'Store',
                'href' => '/admin/settings/store',
                'permission' => 'stores.view',
            ],
            'features' => [
                'key' => 'features',
                'label' => 'Features',
                'href' => '/admin/settings/features',
                'permission' => 'features.view',
            ],
            'security' => [
                'key' => 'security',
                'label' => 'Security',
                'href' => '/admin/settings/health',
                'permission' => 'settings.view',
            ],
        ];

        $byGroup = [];
        foreach ($checks as $check) {
            $group = (string) ($check['group'] ?? '');
            if ($group === '' || ! isset($modules[$group])) {
                continue;
            }
            $byGroup[$group][] = $check;
        }

        $statuses = [];
        foreach ($modules as $key => $module) {
            $groupChecks = $byGroup[$key] ?? [];
            $status = $this->worstStatus($groupChecks);
            $message = $groupChecks[0]['message'] ?? 'No issues reported.';
            foreach ($groupChecks as $check) {
                if (($check['status'] ?? '') === $status) {
                    $message = (string) $check['message'];
                    break;
                }
            }

            $statuses[] = [
                ...$module,
                'status' => $status,
                'message' => $message,
                'check_count' => count($groupChecks),
            ];
        }

        return $statuses;
    }

    /**
     * @param  list<array{status?: string}>  $checks
     */
    private function worstStatus(array $checks): string
    {
        $rank = ['critical' => 3, 'warning' => 2, 'info' => 1, 'healthy' => 0];
        $worst = 'healthy';
        foreach ($checks as $check) {
            $status = (string) ($check['status'] ?? 'info');
            if (($rank[$status] ?? 0) > ($rank[$worst] ?? 0)) {
                $worst = $status;
            }
        }

        return $worst;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function quickActions(): array
    {
        return [
            [
                'key' => 'config_health',
                'label' => 'View configuration health',
                'href' => '/admin/settings/health',
                'permission' => 'settings.view',
            ],
            [
                'key' => 'settings_history',
                'label' => 'Browse settings history',
                'href' => '/admin/settings/history',
                'permission' => 'settings.view',
            ],
            [
                'key' => 'payments',
                'label' => 'Manage payment toggles',
                'href' => '/admin/settings/payments',
                'permission' => 'payments.config.view',
            ],
            [
                'key' => 'shipping',
                'label' => 'Manage shipping rates',
                'href' => '/admin/settings/shipping',
                'permission' => 'shipping.view',
            ],
            [
                'key' => 'notifications',
                'label' => 'Manage notification channels',
                'href' => '/admin/settings/notifications',
                'permission' => 'notifications.view',
            ],
            [
                'key' => 'features',
                'label' => 'Manage feature flags',
                'href' => '/admin/settings/features',
                'permission' => 'features.view',
            ],
            [
                'key' => 'store',
                'label' => 'Manage store settings',
                'href' => '/admin/settings/store',
                'permission' => 'stores.view',
            ],
        ];
    }
}
