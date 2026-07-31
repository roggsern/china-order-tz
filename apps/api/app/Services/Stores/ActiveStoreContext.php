<?php

namespace App\Services\Stores;

use App\Enums\StoreOperationalScope;
use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Support\Admin\AdminPermissions;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Resolves which stores an admin may access.
 * POS helpers keep active-store listing; management helpers include inactive stores.
 */
class ActiveStoreContext
{
    public function assignedStores(Admin $admin): Collection
    {
        if ($admin->is_super_admin) {
            return Store::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        }

        return Store::query()
            ->where('is_active', true)
            ->whereHas('assignments', function ($query) use ($admin) {
                $this->constrainActiveAssignments($query, $admin);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Stores visible in Store Manager / store pickers.
     * Platform creators see all; assigned managers see their stores (incl. inactive);
     * view-only roles without assignments see active stores (catalog picker).
     */
    public function manageableStores(Admin $admin): Collection
    {
        if ($admin->is_super_admin || $admin->hasAdminPermission(AdminPermissions::STORES_CREATE)) {
            return Store::query()->orderBy('sort_order')->orderBy('name')->get();
        }

        $assigned = Store::query()
            ->whereHas('assignments', function ($query) use ($admin) {
                $this->constrainActiveAssignments($query, $admin);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($assigned->isNotEmpty()) {
            return $assigned;
        }

        return Store::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function assertCanAccess(Admin $admin, Store $store): void
    {
        $this->assertCanView($admin, $store);
    }

    public function assertCanView(Admin $admin, Store $store): void
    {
        if (! $this->canView($admin, $store)) {
            throw ValidationException::withMessages([
                'store_id' => ['You are not assigned to this store.'],
            ]);
        }
    }

    public function assertCanManage(Admin $admin, Store $store): void
    {
        if (! $this->canManage($admin, $store)) {
            throw ValidationException::withMessages([
                'store_id' => ['You are not allowed to manage this store.'],
            ]);
        }
    }

    public function canAccess(Admin $admin, Store $store): bool
    {
        return $this->canView($admin, $store);
    }

    /**
     * Read access: super admin, platform creator, or any active store assignment (incl. viewer).
     */
    public function canView(Admin $admin, Store $store): bool
    {
        if ($admin->is_super_admin || $admin->hasAdminPermission(AdminPermissions::STORES_CREATE)) {
            return true;
        }

        return $this->hasActiveAssignment($admin, $store);
    }

    /**
     * Write access: super admin, platform creator, or manager/operator assignment (not viewer).
     */
    public function canManage(Admin $admin, Store $store): bool
    {
        if ($admin->is_super_admin || $admin->hasAdminPermission(AdminPermissions::STORES_CREATE)) {
            return true;
        }

        $scope = $this->operationalScope($admin, $store);

        return $scope !== null && $scope->canManageStore();
    }

    public function isStoreManager(Admin $admin, Store $store): bool
    {
        return $this->operationalScope($admin, $store) === StoreOperationalScope::StoreManager;
    }

    public function isStoreViewer(Admin $admin, Store $store): bool
    {
        return $this->operationalScope($admin, $store) === StoreOperationalScope::StoreViewer;
    }

    public function operationalScope(Admin $admin, Store $store): ?StoreOperationalScope
    {
        $assignment = $this->activeAssignment($admin, $store);

        return $assignment?->operational_scope;
    }

    public function hasActiveAssignment(Admin $admin, Store $store): bool
    {
        return $this->activeAssignment($admin, $store) !== null;
    }

    /**
     * Store cashiers: exactly one active assignment → that store (no selector).
     * Master cashiers / super admin: must pass store_id when ambiguous.
     */
    public function resolveActiveStore(Admin $admin, ?string $storeId = null): Store
    {
        $stores = $this->assignedStores($admin);

        if ($storeId !== null) {
            $store = $stores->firstWhere('id', $storeId)
                ?? Store::query()->find($storeId);

            if ($store === null) {
                throw ValidationException::withMessages([
                    'store_id' => ['Store not found.'],
                ]);
            }

            $this->assertCanAccess($admin, $store);

            return $store;
        }

        if ($admin->is_super_admin) {
            throw ValidationException::withMessages([
                'store_id' => ['Store selection is required.'],
            ]);
        }

        if ($admin->isStoreCashier() && $stores->count() === 1) {
            return $stores->first();
        }

        if ($stores->count() === 1) {
            return $stores->first();
        }

        if ($stores->isEmpty()) {
            throw ValidationException::withMessages([
                'store_id' => ['No store assignment found for this cashier.'],
            ]);
        }

        throw ValidationException::withMessages([
            'store_id' => ['Select an assigned store to continue.'],
        ]);
    }

    public function activeAssignment(Admin $admin, Store $store): ?StoreUserAssignment
    {
        return StoreUserAssignment::query()
            ->where('admin_id', $admin->id)
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->get()
            ->first(fn (StoreUserAssignment $a) => $a->isCurrentlyActive());
    }

    private function constrainActiveAssignments($query, Admin $admin): void
    {
        $query->where('admin_id', $admin->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }
}
