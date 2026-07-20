<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\CmsHomepageSection;

class CmsHomepageSectionPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->canManageCms($admin);
    }

    public function view(Admin $admin, CmsHomepageSection $section): bool
    {
        return $this->canManageCms($admin);
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
