<?php

namespace App\Support\CustomerAgent;

use App\Models\Admin;

/**
 * Operational ownership for Customer Agent pickup actions.
 * Warehouse release cannot be performed by the customer.
 */
final class CustomerAgentPermissions
{
    public static function assert(Admin $admin, string $permission, string $action): void
    {
        if (! $admin->hasAdminPermission($permission)) {
            abort(403, "Admin is not authorized for Customer Agent action: {$action}.");
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    public static function assertAny(Admin $admin, array $permissions, string $action): void
    {
        foreach ($permissions as $permission) {
            if ($admin->hasAdminPermission($permission)) {
                return;
            }
        }

        abort(403, "Admin is not authorized for Customer Agent action: {$action}.");
    }
}
