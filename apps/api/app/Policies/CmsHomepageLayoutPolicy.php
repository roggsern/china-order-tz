<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\CmsHomepageLayout;

/**
 * CMS management is limited to platform admins (not POS cashiers).
 * There is no Spatie permission table — capabilities map to role checks.
 */
class CmsHomepageLayoutPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->canManageCms($admin);
    }

    public function view(Admin $admin, CmsHomepageLayout $layout): bool
    {
        return $this->canManageCms($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->canManageCms($admin);
    }

    public function update(Admin $admin, CmsHomepageLayout $layout): bool
    {
        return $this->canManageCms($admin);
    }

    public function publish(Admin $admin, CmsHomepageLayout $layout): bool
    {
        return $this->canManageCms($admin);
    }

    public function archive(Admin $admin, CmsHomepageLayout $layout): bool
    {
        return $this->canManageCms($admin);
    }

    public function reorder(Admin $admin, CmsHomepageLayout $layout): bool
    {
        return $this->canManageCms($admin);
    }

    public function delete(Admin $admin, CmsHomepageLayout $layout): bool
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

        $slug = $admin->role?->slug;

        return in_array($slug, ['administrator', 'manager'], true);
    }
}
