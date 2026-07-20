<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Canonical local development admin credentials.
     * Override with ADMIN_SEED_EMAIL / ADMIN_SEED_PASSWORD in the API .env if needed.
     */
    public const DEFAULT_EMAIL = 'admin@chinaordertz.com';

    public const DEFAULT_PASSWORD = 'password';

    public function run(): void
    {
        $adminRole = Role::query()->updateOrCreate(
            ['slug' => 'administrator'],
            [
                'name' => 'Administrator',
                'description' => 'Full platform administrator',
            ],
        );

        $email = strtolower(trim((string) env('ADMIN_SEED_EMAIL', self::DEFAULT_EMAIL)));
        $password = (string) env('ADMIN_SEED_PASSWORD', self::DEFAULT_PASSWORD);

        // Empty env overrides (e.g. compose `${VAR:-}`) must not skip seeding.
        if ($email === '') {
            $email = self::DEFAULT_EMAIL;
        }
        if ($password === '') {
            $password = self::DEFAULT_PASSWORD;
        }

        // Plain password — Admin model casts `password` => `hashed` (bcrypt).
        Admin::query()->updateOrCreate(
            ['email' => $email],
            [
                'role_id' => $adminRole->id,
                'name' => 'Super Admin',
                'phone' => '0712345678',
                'password' => $password,
                'is_super_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info("Admin ready: {$email} / (seeded password)");
    }
}
