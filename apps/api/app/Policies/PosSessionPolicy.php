<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\PosSession;
use App\Services\Stores\ActiveStoreContext;

class PosSessionPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_super_admin
            || $admin->isMasterCashier()
            || $admin->isStoreCashier();
    }

    public function view(Admin $admin, PosSession $session): bool
    {
        if ($admin->is_super_admin) {
            return true;
        }

        if (! app(ActiveStoreContext::class)->canAccess($admin, $session->store)) {
            return false;
        }

        if ($admin->isMasterCashier()) {
            return true;
        }

        return $session->admin_id === $admin->id;
    }

    public function open(Admin $admin): bool
    {
        return $admin->is_super_admin
            || $admin->isMasterCashier()
            || $admin->isStoreCashier();
    }

    public function close(Admin $admin, PosSession $session): bool
    {
        if ($admin->is_super_admin) {
            return true;
        }

        return $session->admin_id === $admin->id
            && app(ActiveStoreContext::class)->canAccess($admin, $session->store);
    }

    public function updateFloat(Admin $admin, PosSession $session): bool
    {
        return $this->close($admin, $session) && $session->isOpen();
    }
}
