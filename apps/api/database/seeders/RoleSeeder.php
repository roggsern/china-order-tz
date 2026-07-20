<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Customer', 'slug' => 'customer', 'description' => 'Standard storefront customer'],
            ['name' => 'Administrator', 'slug' => 'administrator', 'description' => 'Full platform administrator'],
            ['name' => 'Manager', 'slug' => 'manager', 'description' => 'Operations and catalog manager'],
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
}
