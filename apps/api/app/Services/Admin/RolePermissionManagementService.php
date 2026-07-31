<?php

namespace App\Services\Admin;

use App\Events\Audit\RolePermissionsUpdatedAudit;
use App\Exceptions\RolePermissionManagementException;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Admin\AdminPermissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RolePermissionManagementService
{
    /**
     * Permissions required for any admin governance path to remain viable without
     * relying on the super-admin bypass.
     *
     * @var list<string>
     */
    private const GOVERNANCE_PERMISSIONS = [
        AdminPermissions::ADMINS_VIEW,
        AdminPermissions::ROLES_MANAGE_PERMISSIONS,
    ];

    /**
     * @param  list<string>  $add
     * @param  list<string>  $remove
     * @return array{added: list<string>, removed: list<string>}
     */
    public function prepareDiff(Role $role, array $add, array $remove, Admin $actor): array
    {
        $this->assertCanManagePermissions($actor);
        $this->assertRoleIsEditable($role);

        $addSlugs = $this->normalizeSlugs($add);
        $removeSlugs = $this->normalizeSlugs($remove);

        if ($addSlugs === [] && $removeSlugs === []) {
            return ['added' => [], 'removed' => []];
        }

        $overlap = array_values(array_intersect($addSlugs, $removeSlugs));
        if ($overlap !== []) {
            throw new RolePermissionManagementException(
                'A permission cannot appear in both add and remove: '.implode(', ', $overlap),
            );
        }

        $this->assertKnownSlugs(array_merge($addSlugs, $removeSlugs));
        $this->assertActorCanGrant($actor, $addSlugs);
        $this->assertNoSelfLockout($actor, $role, $removeSlugs);
        $this->assertMaintainsGovernancePath($role, $removeSlugs);

        $role->load('permissions');
        $currentSlugs = $role->permissions->pluck('slug')->all();

        return [
            'added' => array_values(array_diff($addSlugs, $currentSlugs)),
            'removed' => array_values(array_intersect($removeSlugs, $currentSlugs)),
        ];
    }

    /**
     * @param  list<string>  $add
     * @param  list<string>  $remove
     */
    public function applyDiff(Role $role, array $add, array $remove, Admin $actor): Role
    {
        $diff = $this->prepareDiff($role, $add, $remove, $actor);

        if ($diff['added'] === [] && $diff['removed'] === []) {
            return $role->load('permissions');
        }

        return DB::transaction(function () use ($role, $diff, $actor) {
            if ($diff['removed'] !== []) {
                $removeIds = Permission::query()
                    ->whereIn('slug', $diff['removed'])
                    ->pluck('id')
                    ->all();
                $role->permissions()->detach($removeIds);
            }

            if ($diff['added'] !== []) {
                $attachIds = Permission::query()
                    ->whereIn('slug', $diff['added'])
                    ->pluck('id')
                    ->all();
                $role->permissions()->syncWithoutDetaching($attachIds);
            }

            $role->refresh()->load('permissions');

            event(RolePermissionsUpdatedAudit::fromRole($role, $actor, $diff['added'], $diff['removed']));

            return $role;
        });
    }

    /**
     * @return Collection<int, Permission>
     */
    public function listCatalog(): Collection
    {
        return Permission::query()
            ->whereIn('slug', AdminPermissions::all())
            ->orderBy('domain')
            ->orderBy('slug')
            ->get();
    }

    private function assertCanManagePermissions(Admin $actor): void
    {
        if ($actor->is_super_admin) {
            return;
        }

        if ($actor->hasAdminPermission(AdminPermissions::ROLES_MANAGE_PERMISSIONS)) {
            return;
        }

        throw new RolePermissionManagementException(
            'You are not authorized to manage role permissions.',
            403,
        );
    }

    private function assertRoleIsEditable(Role $role): void
    {
        $protected = config('admin_rbac.protected_role_slugs', []);

        if (in_array($role->slug, $protected, true)) {
            throw new RolePermissionManagementException(
                'Permissions for this role cannot be modified.',
                403,
            );
        }

        if ($role->admins()->where('is_super_admin', true)->exists()) {
            throw new RolePermissionManagementException(
                'Permissions for roles assigned to super admin accounts cannot be modified.',
                403,
            );
        }
    }

    /**
     * @param  list<string>  $slugs
     */
    private function assertKnownSlugs(array $slugs): void
    {
        foreach ($slugs as $slug) {
            if (! AdminPermissions::isKnown($slug)) {
                throw new RolePermissionManagementException("Unknown permission slug: {$slug}.");
            }
        }
    }

    /**
     * @param  list<string>  $addSlugs
     */
    private function assertActorCanGrant(Admin $actor, array $addSlugs): void
    {
        if ($actor->is_super_admin || $addSlugs === []) {
            return;
        }

        foreach ($addSlugs as $slug) {
            if (! $actor->hasAdminPermission($slug)) {
                throw new RolePermissionManagementException(
                    "You cannot grant a permission you do not hold: {$slug}.",
                    403,
                );
            }
        }
    }

    /**
     * @param  list<string>  $removeSlugs
     */
    private function assertNoSelfLockout(Admin $actor, Role $role, array $removeSlugs): void
    {
        if ($actor->is_super_admin || $removeSlugs === []) {
            return;
        }

        if ($actor->role_id !== $role->id) {
            return;
        }

        foreach ($removeSlugs as $slug) {
            if (! $actor->hasAdminPermission($slug)) {
                continue;
            }

            if ($slug === AdminPermissions::ROLES_MANAGE_PERMISSIONS) {
                throw new RolePermissionManagementException(
                    'You cannot remove role permission management from your own role.',
                    422,
                );
            }

            if ($slug === AdminPermissions::ADMINS_VIEW) {
                throw new RolePermissionManagementException(
                    'You cannot remove admin access from your own role.',
                    422,
                );
            }

            throw new RolePermissionManagementException(
                "You cannot remove {$slug} from your own role.",
                422,
            );
        }
    }

    /**
     * @param  list<string>  $removeSlugs
     */
    private function assertMaintainsGovernancePath(Role $role, array $removeSlugs): void
    {
        $governanceRemoved = array_values(array_intersect($removeSlugs, self::GOVERNANCE_PERMISSIONS));
        if ($governanceRemoved === []) {
            return;
        }

        if (Admin::query()->where('is_super_admin', true)->where('is_active', true)->exists()) {
            return;
        }

        $currentSlugs = $role->permissions()->pluck('slug')->all();
        $targetSlugsAfter = array_values(array_diff($currentSlugs, $removeSlugs));

        if ($this->roleHasGovernancePath($targetSlugsAfter)) {
            return;
        }

        $otherRolesHavePath = Role::query()
            ->where('id', '!=', $role->id)
            ->whereNotIn('slug', config('admin_rbac.protected_role_slugs', []))
            ->with('permissions')
            ->get()
            ->contains(fn (Role $other) => $this->roleHasGovernancePath($other->permissionSlugs()));

        if ($otherRolesHavePath) {
            return;
        }

        throw new RolePermissionManagementException(
            'At least one non-protected role must retain admin governance permissions when no active super admin exists.',
            422,
        );
    }

    /**
     * @param  list<string>  $slugs
     */
    private function roleHasGovernancePath(array $slugs): bool
    {
        foreach (self::GOVERNANCE_PERMISSIONS as $required) {
            if (! in_array($required, $slugs, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $slugs
     * @return list<string>
     */
    private function normalizeSlugs(array $slugs): array
    {
        return array_values(array_unique(array_map(
            static fn (string $slug) => strtolower(trim($slug)),
            array_filter($slugs, static fn ($slug) => is_string($slug) && trim($slug) !== ''),
        )));
    }
}
