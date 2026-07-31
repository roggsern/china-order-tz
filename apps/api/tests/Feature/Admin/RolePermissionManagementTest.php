<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin_rbac.preserve_role_permissions' => false]);
        $this->seed(RoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
    }

    public function test_roles_manage_permissions_is_seeded_only_on_administrator_role(): void
    {
        $this->assertTrue(AdminPermissions::isKnown(AdminPermissions::ROLES_MANAGE_PERMISSIONS));

        $administrator = Role::query()->where('slug', 'administrator')->firstOrFail();
        $manager = Role::query()->where('slug', 'manager')->firstOrFail();

        $this->assertTrue($administrator->hasPermission(AdminPermissions::ROLES_MANAGE_PERMISSIONS));
        $this->assertFalse($manager->hasPermission(AdminPermissions::ROLES_MANAGE_PERMISSIONS));
    }

    public function test_permission_catalog_and_update_endpoints_require_roles_manage_permissions(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::ADMINS_VIEW])->create(),
        );

        $this->getJson('/api/v1/admin/permissions')->assertForbidden();

        $role = Role::query()->where('slug', 'warehouse_officer')->firstOrFail();
        $this->patchJson("/api/v1/admin/roles/{$role->id}/permissions", [
            'add' => [AdminPermissions::ORDERS_VIEW],
        ])->assertForbidden();
    }

    public function test_super_admin_can_list_permission_catalog(): void
    {
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $response = $this->getJson('/api/v1/admin/permissions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'permissions' => [
                        ['id', 'slug', 'domain', 'description', 'risk_tier'],
                    ],
                    'permissions_by_domain',
                ],
            ]);

        $ordersView = collect($response->json('data.permissions'))
            ->firstWhere('slug', AdminPermissions::ORDERS_VIEW);
        $this->assertSame('low', $ordersView['risk_tier'] ?? null);

        $paymentsRefund = collect($response->json('data.permissions'))
            ->firstWhere('slug', AdminPermissions::PAYMENTS_REFUND);
        $this->assertSame('high', $paymentsRefund['risk_tier'] ?? null);
    }

    public function test_diff_update_adds_and_removes_permissions_without_full_sync(): void
    {
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $role = Role::query()->where('slug', 'support')->firstOrFail();
        $beforeCount = $role->permissions()->count();

        $this->assertFalse($role->hasPermission(AdminPermissions::ORDERS_CANCEL));

        $this->patchJson("/api/v1/admin/roles/{$role->id}/permissions", [
            'add' => [AdminPermissions::ORDERS_CANCEL],
        ])->assertOk()
            ->assertJsonPath('success', true);

        $role->refresh();
        $this->assertTrue($role->hasPermission(AdminPermissions::ORDERS_CANCEL));
        $this->assertSame($beforeCount + 1, $role->permissions()->count());

        $this->patchJson("/api/v1/admin/roles/{$role->id}/permissions", [
            'remove' => [AdminPermissions::ORDERS_CANCEL],
        ])->assertOk();

        $role->refresh();
        $this->assertFalse($role->hasPermission(AdminPermissions::ORDERS_CANCEL));
        $this->assertSame($beforeCount, $role->permissions()->count());
    }

    public function test_emits_role_permissions_updated_audit_event(): void
    {
        $actor = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($actor);

        $role = Role::query()->where('slug', 'qc_officer')->firstOrFail();

        $this->patchJson("/api/v1/admin/roles/{$role->id}/permissions", [
            'add' => [AdminPermissions::ORDERS_UPDATE],
            'remove' => [AdminPermissions::PROCUREMENT_UPDATE],
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::RolePermissionsUpdated->value,
            'subject_id' => $role->id,
            'actor_id' => $actor->id,
        ]);
    }

    public function test_blocks_protected_administrator_and_customer_roles(): void
    {
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $administrator = Role::query()->where('slug', 'administrator')->firstOrFail();
        $customer = Role::query()->where('slug', 'customer')->firstOrFail();

        $this->patchJson("/api/v1/admin/roles/{$administrator->id}/permissions", [
            'remove' => [AdminPermissions::ADMINS_VIEW],
        ])->assertForbidden();

        $this->patchJson("/api/v1/admin/roles/{$customer->id}/permissions", [
            'add' => [AdminPermissions::ORDERS_VIEW],
        ])->assertForbidden();
    }

    public function test_prevents_privilege_escalation_when_granting_unknown_permissions(): void
    {
        $role = Role::query()->where('slug', 'warehouse_officer')->firstOrFail();

        $actor = Admin::factory()->withPermissions([
            AdminPermissions::ROLES_MANAGE_PERMISSIONS,
            AdminPermissions::ORDERS_VIEW,
        ])->create();

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/admin/roles/{$role->id}/permissions", [
            'add' => [AdminPermissions::PAYMENTS_REFUND],
        ])->assertForbidden()
            ->assertJsonPath('message', 'You cannot grant a permission you do not hold: '.AdminPermissions::PAYMENTS_REFUND.'.');
    }

    public function test_prevents_self_lockout_on_own_role(): void
    {
        $role = Role::query()->where('slug', 'manager')->firstOrFail();

        $actor = Admin::factory()->withPermissions([
            AdminPermissions::ROLES_MANAGE_PERMISSIONS,
            AdminPermissions::ADMINS_VIEW,
            AdminPermissions::ORDERS_VIEW,
        ])->create([
            'role_id' => $role->id,
        ]);

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/admin/roles/{$role->id}/permissions", [
            'remove' => [AdminPermissions::ADMINS_VIEW],
        ])->assertStatus(422)
            ->assertJsonPath('message', 'You cannot remove admin access from your own role.');
    }

    public function test_prevents_removing_last_governance_path_without_super_admin(): void
    {
        Admin::query()->update(['is_super_admin' => false, 'is_active' => true]);

        $viewId = Permission::query()->where('slug', AdminPermissions::ADMINS_VIEW)->value('id');
        $manageId = Permission::query()->where('slug', AdminPermissions::ROLES_MANAGE_PERMISSIONS)->value('id');

        $governanceRole = Role::factory()->create([
            'name' => 'Governance Only',
            'slug' => 'governance_only_test',
        ]);
        $governanceRole->permissions()->sync([$viewId, $manageId]);

        Role::query()
            ->whereNotIn('slug', ['administrator', 'customer', 'governance_only_test'])
            ->each(function (Role $role) use ($viewId, $manageId) {
                $role->permissions()->detach([$viewId, $manageId]);
            });

        $operatorRole = Role::factory()->create([
            'name' => 'Permission Operator',
            'slug' => 'permission_operator_test',
        ]);
        $operatorRole->permissions()->sync([$manageId]);

        $actor = Admin::factory()->ordinary()->create([
            'role_id' => $operatorRole->id,
            'is_super_admin' => false,
        ]);

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/admin/roles/{$governanceRole->id}/permissions", [
            'remove' => [AdminPermissions::ROLES_MANAGE_PERMISSIONS],
        ])->assertStatus(422)
            ->assertJsonPath(
                'message',
                'At least one non-protected role must retain admin governance permissions when no active super admin exists.',
            );
    }

    public function test_seeder_skips_matrix_sync_when_preserve_mode_enabled(): void
    {
        $role = Role::query()->where('slug', 'support')->firstOrFail();
        $permission = Permission::query()->where('slug', AdminPermissions::ORDERS_CANCEL)->firstOrFail();

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $this->assertTrue($role->fresh()->hasPermission(AdminPermissions::ORDERS_CANCEL));

        config(['admin_rbac.preserve_role_permissions' => true]);
        $this->seed(AdminPermissionSeeder::class);

        $this->assertTrue($role->fresh()->hasPermission(AdminPermissions::ORDERS_CANCEL));
    }
}
