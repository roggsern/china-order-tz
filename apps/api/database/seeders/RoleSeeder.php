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
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
