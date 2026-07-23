<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\CmsHomepageLayout;
use App\Support\Admin\AdminPermissions;

class CmsHomepageLayoutPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->canViewCms($admin);
    }

    public function view(Admin $admin, CmsHomepageLayout $layout): bool
    {
        return $this->canViewCms($admin);
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
        return $this->canPublishCms($admin);
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

    private function canViewCms(Admin $admin): bool
    {
        return $admin->hasAdminPermission(AdminPermissions::CMS_VIEW)
            || $admin->hasAdminPermission(AdminPermissions::CMS_MANAGE);
    }

    private function canManageCms(Admin $admin): bool
    {
        return $admin->hasAdminPermission(AdminPermissions::CMS_MANAGE);
    }

    private function canPublishCms(Admin $admin): bool
    {
        return $admin->hasAdminPermission(AdminPermissions::CMS_PUBLISH)
            || $admin->hasAdminPermission(AdminPermissions::CMS_MANAGE);
    }
}
