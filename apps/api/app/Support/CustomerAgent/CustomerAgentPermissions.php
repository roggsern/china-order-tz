<?php

namespace App\Support\CustomerAgent;

use App\Models\Admin;

/**
 * Operational ownership for Customer Agent pickup actions.
 * Warehouse release cannot be performed by the customer.
 */
final class CustomerAgentPermissions
{
    public const LOGISTICS = ['logistics_officer', 'administrator', 'manager'];

    public const WAREHOUSE = ['warehouse_officer', 'administrator', 'manager'];

    /** Handover requires logistics or warehouse operational staff. */
    public const HANDOVER = ['logistics_officer', 'warehouse_officer', 'administrator', 'manager'];

    public static function allows(Admin $admin, array $allowedSlugs): bool
    {
        if ($admin->is_super_admin) {
            return true;
        }

        $slug = $admin->role?->slug;

        return $slug !== null && in_array($slug, $allowedSlugs, true);
    }

    public static function assert(Admin $admin, array $allowedSlugs, string $action): void
    {
        if (! self::allows($admin, $allowedSlugs)) {
            abort(403, "Admin is not authorized for Customer Agent action: {$action}.");
        }
    }
}
