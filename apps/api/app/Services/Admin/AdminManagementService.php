<?php

namespace App\Services\Admin;

use App\Events\Audit\AdminActivatedAudit;
use App\Events\Audit\AdminCreatedAudit;
use App\Events\Audit\AdminDeactivatedAudit;
use App\Events\Audit\AdminRoleAssignedAudit;
use App\Events\Audit\AdminUpdatedAudit;
use App\Exceptions\AdminManagementException;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminManagementService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Admin::query()
            ->with('role')
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '', function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->when(filled($filters['role_id'] ?? null), fn ($query) => $query->where('role_id', $filters['role_id']))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.mb_strtolower($search).'%';
                $query->where(function ($query) use ($term) {
                    $query->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                });
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function show(Admin $admin): Admin
    {
        return $admin->load('role');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Admin $actor): Admin
    {
        return DB::transaction(function () use ($data, $actor) {
            $role = $this->resolveAssignableRole($actor, (string) $data['role_id']);

            $admin = Admin::query()->create([
                'name' => trim((string) $data['name']),
                'email' => strtolower(trim((string) $data['email'])),
                'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
                'password' => (string) $data['password'],
                'role_id' => $role->id,
                'is_super_admin' => false,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
                'email_verified_at' => now(),
            ]);

            event(AdminCreatedAudit::fromAdmin($admin, $actor));

            return $this->show($admin);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Admin $admin, array $data, Admin $actor): Admin
    {
        $this->assertCanManageTarget($actor, $admin);

        return DB::transaction(function () use ($admin, $data, $actor) {
            $before = $this->snapshot($admin);
            $payload = [];

            if (array_key_exists('name', $data)) {
                $payload['name'] = trim((string) $data['name']);
            }

            if (array_key_exists('email', $data)) {
                $payload['email'] = strtolower(trim((string) $data['email']));
            }

            if (array_key_exists('phone', $data)) {
                $payload['phone'] = filled($data['phone']) ? trim((string) $data['phone']) : null;
            }

            if (array_key_exists('password', $data) && filled($data['password'])) {
                $payload['password'] = (string) $data['password'];
            }

            if ($payload !== []) {
                $admin->forceFill($payload)->save();
            }

            $admin->refresh()->load('role');
            $after = $this->snapshot($admin);

            if ($before !== $after) {
                event(AdminUpdatedAudit::fromAdmin($admin, $actor, $before, $after));
            }

            return $this->show($admin);
        });
    }

    public function activate(Admin $admin, Admin $actor): Admin
    {
        $this->assertCanManageTarget($actor, $admin);

        if ($admin->is_active) {
            return $this->show($admin);
        }

        $admin->forceFill(['is_active' => true])->save();
        event(AdminActivatedAudit::fromAdmin($admin->fresh(), $actor));

        return $this->show($admin->fresh());
    }

    public function deactivate(Admin $admin, Admin $actor): Admin
    {
        $this->assertCanManageTarget($actor, $admin);
        $this->assertCanDeactivate($actor, $admin);

        if (! $admin->is_active) {
            return $this->show($admin);
        }

        $admin->forceFill(['is_active' => false])->save();
        event(AdminDeactivatedAudit::fromAdmin($admin->fresh(), $actor));

        return $this->show($admin->fresh());
    }

    public function assignRole(Admin $admin, string $roleId, Admin $actor): Admin
    {
        $this->assertCanManageTarget($actor, $admin);
        $this->assertCanAssignRoleToTarget($actor, $admin);

        $role = $this->resolveAssignableRole($actor, $roleId);
        $previousRole = $admin->role;

        if ($previousRole?->id === $role->id) {
            return $this->show($admin->load('role'));
        }

        $admin->forceFill(['role_id' => $role->id])->save();
        $admin->refresh()->load('role');

        event(AdminRoleAssignedAudit::fromAdmin($admin, $actor, $previousRole, $role));

        return $this->show($admin);
    }

    /**
     * @return list<Role>
     */
    public function listAssignableRoles(Admin $actor): array
    {
        return Role::query()
            ->where('slug', '!=', 'customer')
            ->when(! $actor->is_super_admin, fn ($query) => $query->where('slug', '!=', 'administrator'))
            ->orderBy('name')
            ->get()
            ->all();
    }

    private function resolveAssignableRole(Admin $actor, string $roleId): Role
    {
        $role = Role::query()->find($roleId);

        if ($role === null || $role->slug === 'customer') {
            throw new AdminManagementException('The selected role is not assignable.');
        }

        if ($role->slug === 'administrator' && ! $actor->is_super_admin) {
            throw new AdminManagementException('Only super admins can assign the administrator role.', 403);
        }

        return $role;
    }

    private function assertCanManageTarget(Admin $actor, Admin $target): void
    {
        if ($target->is_super_admin && ! $actor->is_super_admin) {
            throw new AdminManagementException('You cannot manage super admin accounts.', 403);
        }
    }

    private function assertCanDeactivate(Admin $actor, Admin $target): void
    {
        if ($actor->id === $target->id) {
            throw new AdminManagementException('You cannot deactivate your own account.', 422);
        }

        if ($target->is_super_admin && $this->countActiveSuperAdmins() <= 1 && $target->is_active) {
            throw new AdminManagementException('At least one active super admin must remain.', 422);
        }
    }

    private function assertCanAssignRoleToTarget(Admin $actor, Admin $target): void
    {
        if ($actor->id === $target->id && ! $actor->is_super_admin) {
            throw new AdminManagementException('You cannot change your own role.', 422);
        }
    }

    private function countActiveSuperAdmins(): int
    {
        return Admin::query()
            ->where('is_super_admin', true)
            ->where('is_active', true)
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Admin $admin): array
    {
        $admin->loadMissing('role');

        return [
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'role_id' => $admin->role_id,
            'role_slug' => $admin->role?->slug,
            'is_active' => $admin->is_active,
        ];
    }
}
