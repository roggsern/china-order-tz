<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Admin\AdminPermissions;
use Illuminate\Database\Seeder;

/**
 * Idempotent admin permission catalog + role ↔ permission matrix.
 */
class AdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissionIdsBySlug = [];

        foreach (AdminPermissions::all() as $slug) {
            [$domain] = explode('.', $slug, 2);
            $permission = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => AdminPermissions::labels()[$slug] ?? $slug,
                    'domain' => $domain,
                    'description' => "Admin permission: {$slug}",
                ],
            );
            $permissionIdsBySlug[$slug] = $permission->id;
        }

        $this->ensureRoles();

        $matrix = $this->rolePermissionMatrix();

        foreach ($matrix as $roleSlug => $permissionSlugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();
            if ($role === null) {
                continue;
            }

            $ids = [];
            foreach ($permissionSlugs as $slug) {
                if (isset($permissionIdsBySlug[$slug])) {
                    $ids[] = $permissionIdsBySlug[$slug];
                }
            }

            $role->permissions()->sync($ids);
        }

        $this->command?->info('Admin permissions synced for '.count($matrix).' roles.');
    }

    private function ensureRoles(): void
    {
        $roles = [
            ['name' => 'Administrator', 'slug' => 'administrator', 'description' => 'Full platform administrator'],
            ['name' => 'Manager', 'slug' => 'manager', 'description' => 'Operations and catalog manager (legacy)'],
            ['name' => 'Operations Admin', 'slug' => 'operations_admin', 'description' => 'Orders, fulfillment, shipping, limited inventory'],
            ['name' => 'Catalog Manager', 'slug' => 'catalog_manager', 'description' => 'Catalog, configuration, product media'],
            ['name' => 'Finance Admin', 'slug' => 'finance_admin', 'description' => 'Payments, refunds, reconciliation, reports'],
            ['name' => 'Inventory Manager', 'slug' => 'inventory_manager', 'description' => 'Inventory receive/adjust/transfer/restock'],
            ['name' => 'Support', 'slug' => 'support', 'description' => 'Customer support agent'],
            ['name' => 'Master Cashier', 'slug' => 'master_cashier', 'description' => 'POS cashier with multi-store assignments'],
            ['name' => 'Store Cashier', 'slug' => 'store_cashier', 'description' => 'POS cashier assigned to store(s)'],
            ['name' => 'Procurement Officer', 'slug' => 'procurement_officer', 'description' => 'China supplier and purchase order operations'],
            ['name' => 'Warehouse Officer', 'slug' => 'warehouse_officer', 'description' => 'Warehouse receiving and packing operations'],
            ['name' => 'QC Officer', 'slug' => 'qc_officer', 'description' => 'China quality control inspections'],
            ['name' => 'Logistics Officer', 'slug' => 'logistics_officer', 'description' => 'Export readiness, agent handoff, and shipping'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(['slug' => $role['slug']], $role);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function rolePermissionMatrix(): array
    {
        $all = AdminPermissions::all();

        $catalog = [
            AdminPermissions::CATALOG_VIEW,
            AdminPermissions::CATALOG_CREATE,
            AdminPermissions::CATALOG_UPDATE,
            AdminPermissions::CATALOG_PUBLISH,
            AdminPermissions::CATALOG_ARCHIVE,
            AdminPermissions::CATALOG_RESTORE,
            AdminPermissions::CATALOG_DELETE,
            AdminPermissions::PRICING_VIEW,
            AdminPermissions::PRICING_MANAGE,
            AdminPermissions::CONFIGURATION_VIEW,
            AdminPermissions::CONFIGURATION_MANAGE,
            AdminPermissions::PROMOTIONS_VIEW,
            AdminPermissions::PROMOTIONS_CREATE,
            AdminPermissions::PROMOTIONS_UPDATE,
        ];

        $operations = [
            AdminPermissions::ORDERS_VIEW,
            AdminPermissions::ORDERS_UPDATE,
            AdminPermissions::ORDERS_FULFILL,
            AdminPermissions::ORDERS_SHIP,
            AdminPermissions::ORDERS_COMPLETE,
            AdminPermissions::CUSTOMERS_VIEW,
            AdminPermissions::INVENTORY_VIEW,
            AdminPermissions::INVENTORY_RELEASE,
            AdminPermissions::RETURNS_VIEW,
            AdminPermissions::RETURNS_MANAGE,
            AdminPermissions::RETURNS_APPROVE,
            AdminPermissions::RETURNS_REJECT,
            AdminPermissions::REPORTS_VIEW,
            AdminPermissions::WAREHOUSE_VIEW,
            AdminPermissions::WAREHOUSE_JOBS_VIEW,
            AdminPermissions::WAREHOUSE_JOBS_UPDATE,
            AdminPermissions::PROMOTIONS_VIEW,
            AdminPermissions::LOYALTY_VIEW,
            AdminPermissions::GROWTH_VIEW,
            AdminPermissions::NOTIFICATIONS_TEMPLATES_VIEW,
            AdminPermissions::ACTIVITY_LOGS_VIEW,
        ];

        $finance = [
            AdminPermissions::ORDERS_VIEW,
            AdminPermissions::ORDERS_MARK_PAID,
            AdminPermissions::PAYMENTS_VIEW,
            AdminPermissions::PAYMENTS_RECONCILE,
            AdminPermissions::PAYMENTS_RETRY,
            AdminPermissions::PAYMENTS_REFUND,
            AdminPermissions::PAYMENTS_MANAGE_MANUAL,
            AdminPermissions::RETURNS_VIEW,
            AdminPermissions::RETURNS_REFUND,
            AdminPermissions::REPORTS_VIEW,
            AdminPermissions::REPORTS_EXPORT,
            AdminPermissions::ANALYTICS_VIEW,
            AdminPermissions::ANALYTICS_EXPORT,
            AdminPermissions::PROFIT_REPORTS_VIEW,
            AdminPermissions::CUSTOMERS_VIEW,
            AdminPermissions::GROWTH_VIEW,
            AdminPermissions::LOYALTY_VIEW,
            AdminPermissions::ACTIVITY_LOGS_VIEW,
        ];

        $inventory = [
            AdminPermissions::INVENTORY_VIEW,
            AdminPermissions::INVENTORY_RECEIVE,
            AdminPermissions::INVENTORY_ADJUST,
            AdminPermissions::INVENTORY_TRANSFER,
            AdminPermissions::INVENTORY_RESERVE,
            AdminPermissions::INVENTORY_RELEASE,
            AdminPermissions::INVENTORY_RESTOCK,
            AdminPermissions::CATALOG_VIEW,
            AdminPermissions::WAREHOUSE_VIEW,
            AdminPermissions::WAREHOUSE_JOBS_VIEW,
            AdminPermissions::PURCHASE_ORDERS_VIEW,
            AdminPermissions::PURCHASE_ORDERS_RECEIVE,
        ];

        $support = [
            AdminPermissions::CUSTOMERS_VIEW,
            AdminPermissions::CUSTOMERS_UPDATE,
            AdminPermissions::CUSTOMERS_MANAGE_TAGS,
            AdminPermissions::CUSTOMERS_MANAGE_NOTES,
            AdminPermissions::ORDERS_VIEW,
            AdminPermissions::RETURNS_VIEW,
            AdminPermissions::LOYALTY_VIEW,
            AdminPermissions::NOTIFICATIONS_TEMPLATES_VIEW,
        ];

        $cashier = [
            AdminPermissions::INVENTORY_VIEW,
            AdminPermissions::ORDERS_VIEW,
            AdminPermissions::ORDERS_CANCEL,
            AdminPermissions::CUSTOMERS_VIEW,
            AdminPermissions::LOYALTY_VIEW,
            AdminPermissions::LOYALTY_MANAGE,
            AdminPermissions::ANALYTICS_VIEW,
            AdminPermissions::GROWTH_VIEW,
            AdminPermissions::GROWTH_MANAGE,
        ];

        $warehouse = array_values(array_unique(array_merge($inventory, [
            AdminPermissions::ORDERS_VIEW,
            AdminPermissions::ORDERS_FULFILL,
            AdminPermissions::WAREHOUSE_VIEW,
            AdminPermissions::WAREHOUSE_JOBS_VIEW,
            AdminPermissions::WAREHOUSE_JOBS_UPDATE,
            AdminPermissions::WAREHOUSE_JOBS_COMPLETE,
            AdminPermissions::PROCUREMENT_VIEW,
        ])));

        $logistics = [
            AdminPermissions::ORDERS_VIEW,
            AdminPermissions::ORDERS_SHIP,
            AdminPermissions::ORDERS_FULFILL,
            AdminPermissions::CUSTOMERS_VIEW,
            AdminPermissions::INVENTORY_VIEW,
            AdminPermissions::WAREHOUSE_VIEW,
            AdminPermissions::WAREHOUSE_JOBS_VIEW,
            AdminPermissions::WAREHOUSE_JOBS_UPDATE,
            AdminPermissions::WAREHOUSE_JOBS_COMPLETE,
            AdminPermissions::PROCUREMENT_VIEW,
        ];

        $procurement = [
            AdminPermissions::CATALOG_VIEW,
            AdminPermissions::INVENTORY_VIEW,
            AdminPermissions::INVENTORY_RECEIVE,
            AdminPermissions::ORDERS_VIEW,
            AdminPermissions::SUPPLIERS_VIEW,
            AdminPermissions::SUPPLIERS_CREATE,
            AdminPermissions::SUPPLIERS_UPDATE,
            AdminPermissions::PROCUREMENT_VIEW,
            AdminPermissions::PROCUREMENT_CREATE,
            AdminPermissions::PROCUREMENT_UPDATE,
            AdminPermissions::PURCHASE_ORDERS_VIEW,
            AdminPermissions::PURCHASE_ORDERS_CREATE,
            AdminPermissions::PURCHASE_ORDERS_UPDATE,
            AdminPermissions::PURCHASE_ORDERS_APPROVE,
            AdminPermissions::PURCHASE_ORDERS_RECEIVE,
            AdminPermissions::PURCHASE_ORDERS_CANCEL,
        ];

        $qc = [
            AdminPermissions::CATALOG_VIEW,
            AdminPermissions::ORDERS_VIEW,
            AdminPermissions::INVENTORY_VIEW,
            AdminPermissions::PROCUREMENT_VIEW,
            AdminPermissions::PROCUREMENT_UPDATE,
            AdminPermissions::PURCHASE_ORDERS_VIEW,
        ];

        $manager = array_values(array_unique(array_merge(
            $operations,
            $catalog,
            [
                AdminPermissions::CMS_VIEW,
                AdminPermissions::CMS_MANAGE,
                AdminPermissions::CMS_PUBLISH,
                AdminPermissions::CUSTOMERS_UPDATE,
                AdminPermissions::CUSTOMERS_MANAGE_TAGS,
                AdminPermissions::CUSTOMERS_MANAGE_NOTES,
                AdminPermissions::INVENTORY_RECEIVE,
                AdminPermissions::INVENTORY_ADJUST,
                AdminPermissions::SETTINGS_VIEW,
                AdminPermissions::ANALYTICS_VIEW,
                AdminPermissions::PROFIT_REPORTS_VIEW,
                AdminPermissions::GROWTH_VIEW,
                AdminPermissions::GROWTH_MANAGE,
                AdminPermissions::SUPPLIERS_VIEW,
                AdminPermissions::SUPPLIERS_CREATE,
                AdminPermissions::SUPPLIERS_UPDATE,
                AdminPermissions::PROCUREMENT_VIEW,
                AdminPermissions::PROCUREMENT_CREATE,
                AdminPermissions::PROCUREMENT_UPDATE,
                AdminPermissions::PURCHASE_ORDERS_VIEW,
                AdminPermissions::PURCHASE_ORDERS_CREATE,
                AdminPermissions::PURCHASE_ORDERS_UPDATE,
                AdminPermissions::PURCHASE_ORDERS_APPROVE,
                AdminPermissions::PURCHASE_ORDERS_RECEIVE,
                AdminPermissions::WAREHOUSE_JOBS_UPDATE,
                AdminPermissions::WAREHOUSE_JOBS_COMPLETE,
                AdminPermissions::PROMOTIONS_DELETE,
                AdminPermissions::LOYALTY_MANAGE,
                AdminPermissions::NOTIFICATIONS_TEMPLATES_MANAGE,
            ],
        )));

        return [
            'administrator' => $all,
            'manager' => $manager,
            'operations_admin' => $operations,
            'catalog_manager' => $catalog,
            'finance_admin' => $finance,
            'inventory_manager' => $inventory,
            'support' => $support,
            'master_cashier' => $cashier,
            'store_cashier' => $cashier,
            'warehouse_officer' => $warehouse,
            'logistics_officer' => $logistics,
            'procurement_officer' => $procurement,
            'qc_officer' => $qc,
        ];
    }
}
