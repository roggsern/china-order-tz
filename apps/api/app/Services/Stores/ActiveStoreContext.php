<?php

namespace App\Services\Stores;

use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Resolves which stores an admin may access. Super admins see all active stores.
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

    public function assertCanAccess(Admin $admin, Store $store): void
    {
        if (! $this->canAccess($admin, $store)) {
            throw ValidationException::withMessages([
                'store_id' => ['You are not assigned to this store.'],
            ]);
        }
    }

    public function canAccess(Admin $admin, Store $store): bool
    {
        if ($admin->is_super_admin) {
            return true;
        }

        return $this->assignedStores($admin)->contains(fn (Store $s) => $s->id === $store->id);
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
}
