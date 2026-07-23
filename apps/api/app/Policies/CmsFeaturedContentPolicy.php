<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\CmsFeaturedContent;
use App\Support\Admin\AdminPermissions;

class CmsFeaturedContentPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->canViewCms($admin);
    }

    public function view(Admin $admin, CmsFeaturedContent $featured): bool
    {
        return $this->canViewCms($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->canManageCms($admin);
    }

    public function update(Admin $admin, CmsFeaturedContent $featured): bool
    {
        return $this->canManageCms($admin);
    }

    public function reorder(Admin $admin): bool
    {
        return $this->canManageCms($admin);
    }

    public function delete(Admin $admin, CmsFeaturedContent $featured): bool
    {
        return $this->canManageCms($admin);
    }

    private function canViewCms(Admin $admin): bool
    {
        return $admin->hasAdminPermission(AdminPermissions::CMS_VIEW)
            || $admin->hasAdminPermission(AdminPermissions::CMS_MANAGE);
    }

    private function canManageCms(Admin $admin): bool
    {
        return $admin->hasAdminPermission(AdminPermissions::CMS_MANAGE);
    }
}
