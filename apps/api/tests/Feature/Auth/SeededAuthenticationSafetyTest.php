<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\EcommerceSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeededAuthenticationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_admin_can_authenticate(): void
    {
        $this->seed([RoleSeeder::class, AdminSeeder::class]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => AdminSeeder::DEFAULT_EMAIL,
            'password' => AdminSeeder::DEFAULT_PASSWORD,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token']);

        $admin = Admin::query()->where('email', AdminSeeder::DEFAULT_EMAIL)->firstOrFail();

        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check(AdminSeeder::DEFAULT_PASSWORD, $admin->getAuthPassword()));
    }

    public function test_seeded_customer_can_authenticate_with_single_hash(): void
    {
        $this->seed([RoleSeeder::class, EcommerceSeeder::class]);

        $user = User::query()->where('email', EcommerceSeeder::DEFAULT_EMAIL)->firstOrFail();
        $hash = $user->getAuthPassword();

        $this->assertTrue(Hash::isHashed($hash));
        $this->assertMatchesRegularExpression('/^\$2[ayb]\$/', $hash);
        $this->assertTrue(Hash::check(EcommerceSeeder::DEFAULT_PASSWORD, $hash));
        // Double-hashed bcrypt would fail the plain seed password check above.
        $response = $this->postJson('/api/v1/login', [
            'email' => EcommerceSeeder::DEFAULT_EMAIL,
            'password' => EcommerceSeeder::DEFAULT_PASSWORD,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token']);

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_unauthenticated_admin_api_returns_401(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/admin/products')->assertUnauthorized();
        $this->getJson('/api/v1/admin/me')->assertUnauthorized();
    }

    public function test_unauthenticated_customer_api_returns_401(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/cart')->assertUnauthorized();
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }

    public function test_reseeding_does_not_duplicate_identities_or_roles(): void
    {
        $this->seed([RoleSeeder::class, AdminSeeder::class, EcommerceSeeder::class]);

        $roles = Role::query()->count();
        $admins = Admin::query()->count();
        $demoCustomers = User::query()->where('email', EcommerceSeeder::DEFAULT_EMAIL)->count();

        $this->seed([RoleSeeder::class, AdminSeeder::class, EcommerceSeeder::class]);

        $this->assertSame($roles, Role::query()->count());
        $this->assertSame($admins, Admin::query()->count());
        $this->assertSame(1, $demoCustomers);
        $this->assertSame(1, User::query()->where('email', EcommerceSeeder::DEFAULT_EMAIL)->count());
        $this->assertSame(1, Admin::query()->where('email', AdminSeeder::DEFAULT_EMAIL)->count());
    }
}
