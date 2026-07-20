<?php

namespace App\Support\China;

use App\Models\Admin;

/**
 * Operational ownership for China Workflow actions.
 * Super admin / administrator / manager may perform any China action.
 */
final class ChinaWorkflowPermissions
{
    public const PROCUREMENT = ['procurement_officer', 'administrator', 'manager'];

    public const WAREHOUSE = ['warehouse_officer', 'administrator', 'manager'];

    public const QC = ['qc_officer', 'administrator', 'manager'];

    public const LOGISTICS = ['logistics_officer', 'administrator', 'manager'];

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
            abort(403, "Admin is not authorized for China Workflow action: {$action}.");
        }
    }
}
