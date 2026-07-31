<?php

namespace App\Services\ConfigurationHealth\Checks;

use App\Models\Admin;
use App\Models\Role;
use App\Services\ConfigurationHealth\Concerns\BuildsHealthCheckResult;
use App\Services\ConfigurationHealth\Contracts\ConfigurationHealthCheck;
use App\Support\Admin\PermissionRiskClassifier;
use App\Support\Admin\PermissionRiskTier;
use App\Support\Settings\SettingsDefinitions;
use App\Support\Settings\SettingsSecretGuard;
use Throwable;

final class SecurityHealthCheck implements ConfigurationHealthCheck
{
    use BuildsHealthCheckResult;

    public function run(): array
    {
        try {
            $results = [];

            foreach (array_keys(SettingsDefinitions::all()) as $key) {
                if (SettingsSecretGuard::isSecretKey($key)) {
                    $results[] = $this->result(
                        'security',
                        'critical',
                        'Settings catalog contains a secret-like key definition.',
                    );
                    break;
                }
            }

            $inactiveElevated = Admin::query()
                ->where('is_active', false)
                ->where(function ($query): void {
                    $query->where('is_super_admin', true)
                        ->orWhereHas('role.permissions', function ($permissions): void {
                            $permissions->whereIn('slug', $this->highRiskSlugs());
                        });
                })
                ->count();

            if ($inactiveElevated > 0) {
                $results[] = $this->result(
                    'security',
                    'warning',
                    "{$inactiveElevated} inactive admin account(s) still carry elevated or high-risk access.",
                );
            }

            $rolesWithManyHighRisk = Role::query()
                ->with('permissions:id,slug')
                ->get()
                ->filter(function (Role $role): bool {
                    $high = 0;
                    foreach ($role->permissions as $permission) {
                        if (PermissionRiskClassifier::classify((string) $permission->slug) === PermissionRiskTier::High) {
                            $high++;
                        }
                    }

                    return $high >= 8;
                })
                ->count();

            if ($rolesWithManyHighRisk > 0) {
                $results[] = $this->result(
                    'security',
                    'info',
                    "{$rolesWithManyHighRisk} role(s) grant a large number of high-risk permissions.",
                );
            }

            if ($results === []) {
                $results[] = $this->result(
                    'security',
                    'healthy',
                    'No obvious configuration security issues detected.',
                );
            }

            return $results;
        } catch (Throwable) {
            return [
                $this->result('security', 'critical', 'Unable to resolve security configuration signals.'),
            ];
        }
    }

    /**
     * @return list<string>
     */
    private function highRiskSlugs(): array
    {
        $slugs = [];
        foreach (\App\Support\Admin\AdminPermissions::all() as $slug) {
            if (PermissionRiskClassifier::classify($slug) === PermissionRiskTier::High) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }
}
