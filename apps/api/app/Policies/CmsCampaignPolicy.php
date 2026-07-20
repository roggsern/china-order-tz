<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\CmsCampaign;

class CmsCampaignPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->canManageCms($admin);
    }

    public function view(Admin $admin, CmsCampaign $campaign): bool
    {
        return $this->canManageCms($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->canManageCms($admin);
    }

    public function update(Admin $admin, CmsCampaign $campaign): bool
    {
        return $this->canManageCms($admin);
    }

    public function publish(Admin $admin, CmsCampaign $campaign): bool
    {
        return $this->canManageCms($admin);
    }

    public function archive(Admin $admin, CmsCampaign $campaign): bool
    {
        return $this->canManageCms($admin);
    }

    private function canManageCms(Admin $admin): bool
    {
        if (! $admin->is_active) {
            return false;
        }

        if ($admin->is_super_admin) {
            return true;
        }

        return in_array($admin->role?->slug, ['administrator', 'manager'], true);
    }
}
