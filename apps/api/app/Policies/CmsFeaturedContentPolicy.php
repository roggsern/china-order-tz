<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\CmsFeaturedContent;

class CmsFeaturedContentPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->canManageCms($admin);
    }

    public function view(Admin $admin, CmsFeaturedContent $featured): bool
    {
        return $this->canManageCms($admin);
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
