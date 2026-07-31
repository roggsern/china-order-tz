<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Admin\AdminPermissions;
use App\Support\Admin\PermissionRiskClassifier;
use App\Support\Admin\PermissionRiskTier;
use App\Support\Admin\RolePermissionImpactWarning;

class RolePermissionImpactService
{
    /**
     * @var list<string>
     */
    private const ADMIN_ACCESS_PERMISSIONS = [
        AdminPermissions::ADMINS_VIEW,
        AdminPermissions::ROLES_MANAGE_PERMISSIONS,
        AdminPermissions::ADMINS_CREATE,
        AdminPermissions::ADMINS_UPDATE,
        AdminPermissions::ADMINS_ACTIVATE,
        AdminPermissions::ADMINS_DEACTIVATE,
        AdminPermissions::ADMINS_ASSIGN_ROLES,
    ];

    public function __construct(
        private readonly RolePermissionManagementService $permissions,
    ) {}

    /**
     * @param  list<string>  $add
     * @param  list<string>  $remove
     * @return array<string, mixed>
     */
    public function preview(Role $role, array $add, array $remove, Admin $actor): array
    {
        $diff = $this->permissions->prepareDiff($role, $add, $remove, $actor);
        $role->loadCount(['admins']);

        $affectedAdmins = Admin::query()
            ->where('role_id', $role->id)
            ->where('is_active', true)
            ->count();

        $addedPermissions = $this->formatPermissions($diff['added']);
        $removedPermissions = $this->formatPermissions($diff['removed']);

        return [
            'role' => (new \App\Http\Resources\RoleSummaryResource(
                $role->loadCount(['admins', 'permissions', 'users']),
            ))->resolve(),
            'affected_admins' => $affectedAdmins,
            'added_permissions' => $addedPermissions,
            'removed_permissions' => $removedPermissions,
            'warnings' => $this->buildWarnings(
                $addedPermissions,
                $removedPermissions,
                $affectedAdmins,
            ),
        ];
    }

    /**
     * @param  list<string>  $slugs
     * @return list<array<string, mixed>>
     */
    private function formatPermissions(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        return Permission::query()
            ->whereIn('slug', $slugs)
            ->orderBy('domain')
            ->orderBy('slug')
            ->get()
            ->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'slug' => $permission->slug,
                'domain' => $permission->domain,
                'name' => $permission->name,
                'description' => $permission->description,
                'risk_tier' => PermissionRiskClassifier::classify($permission->slug)->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $addedPermissions
     * @param  list<array<string, mixed>>  $removedPermissions
     * @return list<array<string, mixed>>
     */
    private function buildWarnings(
        array $addedPermissions,
        array $removedPermissions,
        int $affectedAdmins,
    ): array {
        $warnings = [];

        $highRiskAdded = array_values(array_filter(
            $addedPermissions,
            fn (array $permission) => ($permission['risk_tier'] ?? null) === PermissionRiskTier::High->value,
        ));

        if ($highRiskAdded !== []) {
            $warnings[] = $this->warning(
                RolePermissionImpactWarning::HighRiskPermissionAdded,
                'One or more high-risk permissions would be granted to this role.',
                array_column($highRiskAdded, 'slug'),
            );
        }

        $adminAccessRemoved = array_values(array_filter(
            $removedPermissions,
            fn (array $permission) => in_array($permission['slug'], self::ADMIN_ACCESS_PERMISSIONS, true),
        ));

        if ($adminAccessRemoved !== []) {
            $warnings[] = $this->warning(
                RolePermissionImpactWarning::AdminAccessReduction,
                'This change would reduce admin access for users assigned to this role.',
                array_column($adminAccessRemoved, 'slug'),
            );
        }

        if ($affectedAdmins > 1) {
            $warnings[] = $this->warning(
                RolePermissionImpactWarning::MultipleUsersAffected,
                sprintf('%d active admins assigned to this role would be affected.', $affectedAdmins),
                [],
            );
        }

        return $warnings;
    }

    /**
     * @param  list<string>  $permissions
     * @return array<string, mixed>
     */
    private function warning(
        RolePermissionImpactWarning $code,
        string $message,
        array $permissions,
    ): array {
        return [
            'code' => $code->value,
            'label' => $code->label(),
            'message' => $message,
            'permissions' => $permissions,
        ];
    }
}
