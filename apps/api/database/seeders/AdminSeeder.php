<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::query()->where('slug', 'administrator')->firstOrFail();

        Admin::query()->updateOrCreate(
            ['email' => 'admin@chinaordertz.com'],
            [
                'role_id' => $adminRole->id,
                'name' => 'Super Admin',
                'phone' => '0712345678',
                'password' => Hash::make('password'),
                'is_super_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        Admin::factory(3)->create([
            'role_id' => $adminRole->id,
        ]);
    }
}
