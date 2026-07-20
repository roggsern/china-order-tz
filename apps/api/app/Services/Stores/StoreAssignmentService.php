<?php

namespace App\Services\Stores;

use App\Enums\StoreAssignmentType;
use App\Events\Audit\StorePlatformAudit;
use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use Illuminate\Validation\ValidationException;

class StoreAssignmentService
{
    public function assign(
        Admin $cashier,
        Store $store,
        Admin $actor,
        StoreAssignmentType $type = StoreAssignmentType::Permanent,
        ?\DateTimeInterface $startsAt = null,
        ?\DateTimeInterface $endsAt = null,
    ): StoreUserAssignment {
        if (! $actor->is_super_admin) {
            throw ValidationException::withMessages([
                'admin' => ['Only super admins may manage store assignments.'],
            ]);
        }

        $assignment = StoreUserAssignment::query()->updateOrCreate(
            [
                'admin_id' => $cashier->id,
                'store_id' => $store->id,
            ],
            [
                'assignment_type' => $type,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_active' => true,
                'assigned_by' => $actor->id,
            ],
        );

        event(StorePlatformAudit::cashierAssigned($assignment, $actor));

        return $assignment->fresh(['admin', 'store']);
    }

    public function revoke(Admin $cashier, Store $store, Admin $actor): StoreUserAssignment
    {
        if (! $actor->is_super_admin) {
            throw ValidationException::withMessages([
                'admin' => ['Only super admins may manage store assignments.'],
            ]);
        }

        $assignment = StoreUserAssignment::query()
            ->where('admin_id', $cashier->id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $assignment->forceFill(['is_active' => false, 'ends_at' => now()])->save();

        event(StorePlatformAudit::cashierRemoved($assignment, $actor));

        return $assignment->fresh(['admin', 'store']);
    }
}
