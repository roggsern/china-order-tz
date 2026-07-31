<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Role;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRoleReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
    }

    public function test_role_list_includes_counts_and_requires_admins_view(): void
    {
        $managerRole = Role::query()->where('slug', 'manager')->firstOrFail();
        Admin::factory()->ordinary()->count(2)->create(['role_id' => $managerRole->id]);

        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());
        $this->getJson('/api/v1/admin/roles')->assertForbidden();

        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::ADMINS_VIEW])->create(),
        );

        $response = $this->getJson('/api/v1/admin/roles')
            ->assertOk()
            ->assertJsonPath('success', true);

        $manager = collect($response->json('data'))->firstWhere('slug', 'manager');
        $this->assertNotNull($manager);
        $this->assertSame(2, $manager['users_count']);
        $this->assertGreaterThan(0, $manager['permissions_count']);
        $this->assertArrayHasKey('name', $manager);
        $this->assertArrayHasKey('slug', $manager);
        $this->assertArrayHasKey('description', $manager);
    }

    public function test_role_detail_includes_assigned_admins_and_grouped_permissions(): void
    {
        $managerRole = Role::query()->where('slug', 'manager')->firstOrFail();
        $assigned = Admin::factory()->ordinary()->create([
            'role_id' => $managerRole->id,
            'name' => 'Ops Manager',
            'email' => 'ops.manager@example.com',
        ]);

        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::ADMINS_VIEW])->create(),
        );

        $response = $this->getJson("/api/v1/admin/roles/{$managerRole->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.slug', 'manager')
            ->assertJsonPath('data.assigned_admins.0.id', $assigned->id)
            ->assertJsonPath('data.assigned_admins.0.email', 'ops.manager@example.com');

        $groups = $response->json('data.permissions_by_domain');
        $this->assertIsArray($groups);
        $this->assertNotEmpty($groups);

        $ordersGroup = collect($groups)->firstWhere('domain', 'orders');
        $this->assertNotNull($ordersGroup);
        $this->assertNotEmpty($ordersGroup['permissions']);
        $this->assertContains(
            'orders.view',
            collect($ordersGroup['permissions'])->pluck('slug')->all(),
        );
        $this->assertSame(
            'low',
            collect($ordersGroup['permissions'])->firstWhere('slug', 'orders.view')['risk_tier'] ?? null,
        );

        foreach ($groups as $group) {
            $this->assertArrayHasKey('domain', $group);
            $this->assertArrayHasKey('permissions', $group);
            foreach ($group['permissions'] as $permission) {
                $this->assertSame($group['domain'], $permission['domain']);
            }
        }
    }

    public function test_assignable_role_query_still_filters_for_admin_assignment_dropdown(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::ADMINS_VIEW])->create(),
        );

        $response = $this->getJson('/api/v1/admin/roles?assignable=1')->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertNotContains('customer', $slugs);
        $this->assertNotContains('administrator', $slugs);
        $this->assertContains('warehouse_officer', $slugs);
    }

    public function test_super_admin_can_view_full_role_matrix(): void
    {
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $this->getJson('/api/v1/admin/roles')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'administrator']);

        $administratorRole = Role::query()->where('slug', 'administrator')->firstOrFail();

        $this->getJson("/api/v1/admin/roles/{$administratorRole->id}")
            ->assertOk()
            ->assertJsonPath('data.role.slug', 'administrator');
    }
}
