<?php

namespace App\Services\Stores;

use App\Enums\StoreAssignmentType;
use App\Enums\StoreOperationalScope;
use App\Events\Audit\StoreTeamAssignedAudit;
use App\Events\Audit\StoreTeamRemovedAudit;
use App\Events\Audit\StoreTeamUpdatedAudit;
use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Support\Admin\AdminPermissions;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Store team assignment CRUD — operational scope on top of store_user_assignments.
 * Does not replace global admin RBAC roles.
 */
class StoreTeamService
{
    public function __construct(
        private readonly ActiveStoreContext $storeContext,
    ) {}

    public function canViewTeam(Admin $admin, Store $store): bool
    {
        if ($admin->is_super_admin) {
            return true;
        }

        if ($admin->hasAdminPermission(AdminPermissions::STORES_TEAM_VIEW)
            || $admin->hasAdminPermission(AdminPermissions::STORES_ASSIGN)
            || $admin->hasAdminPermission(AdminPermissions::STORES_CREATE)) {
            return $this->storeContext->canView($admin, $store);
        }

        return $this->storeContext->hasActiveAssignment($admin, $store);
    }

    public function canManageTeam(Admin $admin, Store $store): bool
    {
        if ($admin->is_super_admin) {
            return true;
        }

        if ($admin->hasAdminPermission(AdminPermissions::STORES_TEAM_MANAGE)
            || $admin->hasAdminPermission(AdminPermissions::STORES_ASSIGN)
            || $admin->hasAdminPermission(AdminPermissions::STORES_CREATE)) {
            return $this->storeContext->canView($admin, $store);
        }

        return $this->storeContext->isStoreManager($admin, $store);
    }

    public function assertCanViewTeam(Admin $admin, Store $store): void
    {
        if (! $this->canViewTeam($admin, $store)) {
            throw ValidationException::withMessages([
                'store_id' => ['You are not allowed to view this store team.'],
            ]);
        }
    }

    public function assertCanManageTeam(Admin $admin, Store $store): void
    {
        if (! $this->canManageTeam($admin, $store)) {
            throw ValidationException::withMessages([
                'store_id' => ['You are not allowed to manage this store team.'],
            ]);
        }
    }

    /**
     * @return Collection<int, StoreUserAssignment>
     */
    public function listTeam(Store $store): Collection
    {
        return StoreUserAssignment::query()
            ->with([
                'admin:id,name,email,is_active,role_id',
                'admin.role:id,name,slug',
                'assignedByAdmin:id,name,email',
            ])
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (StoreUserAssignment $a) => $a->isCurrentlyActive())
            ->values();
    }

    public function assign(
        Admin $member,
        Store $store,
        Admin $actor,
        StoreOperationalScope $scope,
        StoreAssignmentType $type = StoreAssignmentType::Permanent,
        ?\DateTimeInterface $startsAt = null,
        ?\DateTimeInterface $endsAt = null,
    ): StoreUserAssignment {
        $this->assertCanManageTeam($actor, $store);

        $assignment = StoreUserAssignment::query()->updateOrCreate(
            [
                'admin_id' => $member->id,
                'store_id' => $store->id,
            ],
            [
                'assignment_type' => $type,
                'operational_scope' => $scope,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_active' => true,
                'assigned_by' => $actor->id,
            ],
        );

        event(StoreTeamAssignedAudit::fromAssignment($assignment->fresh(), $actor));

        return $assignment->fresh(['admin.role', 'assignedByAdmin']);
    }

    public function update(
        Admin $member,
        Store $store,
        Admin $actor,
        StoreOperationalScope $scope,
        StoreAssignmentType $type,
        ?\DateTimeInterface $startsAt = null,
        ?\DateTimeInterface $endsAt = null,
    ): StoreUserAssignment {
        $this->assertCanManageTeam($actor, $store);

        $assignment = StoreUserAssignment::query()
            ->where('admin_id', $member->id)
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->firstOrFail();

        $previous = [
            'operational_scope' => $assignment->operational_scope?->value,
            'assignment_type' => $assignment->assignment_type?->value,
            'starts_at' => $assignment->starts_at?->toIso8601String(),
            'ends_at' => $assignment->ends_at?->toIso8601String(),
        ];

        $assignment->forceFill([
            'operational_scope' => $scope,
            'assignment_type' => $type,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_active' => true,
            'assigned_by' => $actor->id,
        ])->save();

        event(StoreTeamUpdatedAudit::fromAssignment($assignment->fresh(), $previous, $actor));

        return $assignment->fresh(['admin.role', 'assignedByAdmin']);
    }

    public function remove(Admin $member, Store $store, Admin $actor): StoreUserAssignment
    {
        $this->assertCanManageTeam($actor, $store);

        $assignment = StoreUserAssignment::query()
            ->where('admin_id', $member->id)
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->firstOrFail();

        $assignment->forceFill(['is_active' => false, 'ends_at' => now()])->save();

        event(StoreTeamRemovedAudit::fromAssignment($assignment->fresh(), $actor));

        return $assignment->fresh(['admin.role', 'assignedByAdmin']);
    }

    /**
     * Stores the admin may operate on (assignments + platform scope).
     *
     * @return Collection<int, Store>
     */
    public function myStores(Admin $admin): Collection
    {
        if ($admin->is_super_admin || $admin->hasAdminPermission(AdminPermissions::STORES_CREATE)) {
            return Store::query()->orderBy('sort_order')->orderBy('name')->get();
        }

        return Store::query()
            ->whereHas('assignments', function ($query) use ($admin) {
                $query->where('admin_id', $admin->id)
                    ->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                    });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
