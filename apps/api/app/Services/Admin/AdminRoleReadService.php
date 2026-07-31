<?php

namespace App\Services\Admin;

use App\Models\Role;
use App\Support\Admin\PermissionRiskClassifier;
use Illuminate\Database\Eloquent\Collection;

class AdminRoleReadService
{
    /**
     * @return Collection<int, Role>
     */
    public function list(): Collection
    {
        return Role::query()
            ->withCount(['admins', 'permissions', 'users'])
            ->orderBy('name')
            ->get();
    }

    public function show(Role $role): Role
    {
        return $role->load([
            'permissions' => fn ($query) => $query->orderBy('domain')->orderBy('slug'),
            'admins' => fn ($query) => $query->orderBy('name'),
        ])->loadCount(['admins', 'permissions', 'users']);
    }

    /**
     * @param  Collection<int, \App\Models\Permission>  $permissions
     * @return list<array{domain: string, permissions: list<array<string, mixed>>}>
     */
    public function groupPermissionsByDomain(Collection $permissions): array
    {
        return $permissions
            ->groupBy(fn ($permission) => $permission->domain ?: explode('.', (string) $permission->slug, 2)[0])
            ->sortKeys()
            ->map(function (Collection $items, string $domain) {
                return [
                    'domain' => $domain,
                    'permissions' => $items
                        ->sortBy('slug')
                        ->values()
                        ->map(fn ($permission) => [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'slug' => $permission->slug,
                            'domain' => $permission->domain,
                            'description' => $permission->description,
                            'risk_tier' => PermissionRiskClassifier::classify($permission->slug)->value,
                        ])
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function resolveUsersCount(Role $role): int
    {
        if ($role->slug === 'customer') {
            return (int) ($role->users_count ?? $role->users()->count());
        }

        return (int) ($role->admins_count ?? $role->admins()->count());
    }
}
