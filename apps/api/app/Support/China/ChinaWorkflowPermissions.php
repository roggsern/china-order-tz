<?php

namespace App\Support\China;

use App\Models\Admin;

final class ChinaWorkflowPermissions
{
    public static function assert(Admin $admin, string $permission, string $action): void
    {
        if (! $admin->hasAdminPermission($permission)) {
            abort(403, "Admin is not authorized for China Workflow action: {$action}.");
        }
    }
}
