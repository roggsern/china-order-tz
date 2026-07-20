<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\PosReceipt;
use App\Services\Stores\ActiveStoreContext;

class PosReceiptPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_super_admin
            || $admin->isMasterCashier()
            || $admin->isStoreCashier();
    }

    public function view(Admin $admin, PosReceipt $receipt): bool
    {
        if ($admin->is_super_admin) {
            return true;
        }

        return app(ActiveStoreContext::class)->canAccess($admin, $receipt->store);
    }

    public function print(Admin $admin, PosReceipt $receipt): bool
    {
        return $this->view($admin, $receipt);
    }

    public function reprint(Admin $admin, PosReceipt $receipt): bool
    {
        return $this->view($admin, $receipt);
    }
}
