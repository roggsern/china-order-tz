<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\CmsHomepageSection;
use App\Support\Admin\AdminPermissions;

class CmsHomepageSectionPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->canViewCms($admin);
    }

    public function view(Admin $admin, CmsHomepageSection $section): bool
    {
        return $this->canViewCms($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->canManageCms($admin);
    }

    public function update(Admin $admin, CmsHomepageSection $section): bool
    {
        return $this->canManageCms($admin);
    }

    public function delete(Admin $admin, CmsHomepageSection $section): bool
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
