<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Preserve role permission matrix on seeder runs
    |--------------------------------------------------------------------------
    |
    | When true, AdminPermissionSeeder still upserts the permission catalog but
    | skips role ↔ permission sync(). Use in production after go-live so manual
    | or API-driven permission changes are not overwritten by deploy seeds.
    |
    | Local/testing defaults to false so RoleSeeder keeps the baseline matrix.
    |
    */
    'preserve_role_permissions' => (bool) env(
        'ADMIN_RBAC_PRESERVE_ROLE_PERMISSIONS',
        env('APP_ENV') === 'production',
    ),

    /*
    | Roles whose permission matrix must never be edited via the governance API.
    */
    'protected_role_slugs' => [
        'administrator',
        'customer',
    ],

];
