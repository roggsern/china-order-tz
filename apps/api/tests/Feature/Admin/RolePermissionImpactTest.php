<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolePermissionImpactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin_rbac.preserve_role_permissions' => false]);
        $this->seed(RoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
    }

    public function test_preview_returns_expected_response_shape(): void
    {
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $role = Role::query()->where('slug', 'support')->firstOrFail();
        $beforeCount = $role->permissions()->count();

        $response = $this->postJson("/api/v1/admin/roles/{$role->id}/permissions/preview", [
            'add' => [AdminPermissions::ORDERS_UPDATE],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'role' => ['id', 'name', 'slug'],
                    'affected_admins',
                    'added_permissions',
                    'removed_permissions',
                    'warnings',
                ],
            ]);

        $added = collect($response->json('data.added_permissions'));
        $this->assertTrue($added->contains('slug', AdminPermissions::ORDERS_UPDATE));
        $this->assertSame('medium', $added->firstWhere('slug', AdminPermissions::ORDERS_UPDATE)['risk_tier'] ?? null);
        $this->assertSame($beforeCount, $role->fresh()->permissions()->count());
    }

    public function test_preview_detects_high_risk_permission_warning(): void
    {
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $role = Role::query()->where('slug', 'support')->firstOrFail();

        $response = $this->postJson("/api/v1/admin/roles/{$role->id}/permissions/preview", [
            'add' => [AdminPermissions::PAYMENTS_REFUND],
        ])->assertOk();

        $warnings = collect($response->json('data.warnings'));
        $highRisk = $warnings->firstWhere('code', 'HIGH_RISK_PERMISSION_ADDED');

        $this->assertNotNull($highRisk);
        $this->assertContains(AdminPermissions::PAYMENTS_REFUND, $highRisk['permissions']);
    }

    public function test_preview_detects_admin_access_reduction_warning(): void
    {
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $role = Role::query()->where('slug', 'warehouse_officer')->firstOrFail();
        $viewId = Permission::query()->where('slug', AdminPermissions::ADMINS_VIEW)->value('id');
        $role->permissions()->syncWithoutDetaching([$viewId]);
        $this->assertTrue($role->fresh()->hasPermission(AdminPermissions::ADMINS_VIEW));

        $response = $this->postJson("/api/v1/admin/roles/{$role->id}/permissions/preview", [
            'remove' => [AdminPermissions::ADMINS_VIEW],
        ])->assertOk();

        $warnings = collect($response->json('data.warnings'));
        $reduction = $warnings->firstWhere('code', 'ADMIN_ACCESS_REDUCTION');

        $this->assertNotNull($reduction);
        $this->assertContains(AdminPermissions::ADMINS_VIEW, $reduction['permissions']);
        $this->assertTrue(
            collect($response->json('data.removed_permissions'))->contains('slug', AdminPermissions::ADMINS_VIEW),
        );
    }

    public function test_preview_counts_affected_active_admins_and_multiple_users_warning(): void
    {
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $role = Role::query()->where('slug', 'support')->firstOrFail();

        Admin::factory()->ordinary()->count(2)->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        Admin::factory()->ordinary()->inactive()->create([
            'role_id' => $role->id,
        ]);

        $response = $this->postJson("/api/v1/admin/roles/{$role->id}/permissions/preview", [
            'add' => [AdminPermissions::ORDERS_UPDATE],
        ])->assertOk();

        $this->assertSame(2, $response->json('data.affected_admins'));

        $warnings = collect($response->json('data.warnings'));
        $multiple = $warnings->firstWhere('code', 'MULTIPLE_USERS_AFFECTED');
        $this->assertNotNull($multiple);
        $this->assertStringContainsString('2 active admins', $multiple['message']);
    }

    public function test_preview_does_not_mutate_role_permissions(): void
    {
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $role = Role::query()->where('slug', 'warehouse_officer')->firstOrFail();
        $beforeSlugs = $role->permissions()->pluck('slug')->sort()->values()->all();

        $this->postJson("/api/v1/admin/roles/{$role->id}/permissions/preview", [
            'add' => [AdminPermissions::PAYMENTS_REFUND],
            'remove' => [AdminPermissions::ORDERS_VIEW],
        ])->assertOk();

        $afterSlugs = $role->fresh()->permissions()->pluck('slug')->sort()->values()->all();
        $this->assertSame($beforeSlugs, $afterSlugs);
    }

    public function test_preview_requires_roles_manage_permissions(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::ADMINS_VIEW])->create(),
        );

        $role = Role::query()->where('slug', 'support')->firstOrFail();

        $this->postJson("/api/v1/admin/roles/{$role->id}/permissions/preview", [
            'add' => [AdminPermissions::ORDERS_VIEW],
        ])->assertForbidden();
    }

    public function test_preview_blocks_protected_roles(): void
    {
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $administrator = Role::query()->where('slug', 'administrator')->firstOrFail();
        $customer = Role::query()->where('slug', 'customer')->firstOrFail();

        $this->postJson("/api/v1/admin/roles/{$administrator->id}/permissions/preview", [
            'remove' => [AdminPermissions::ADMINS_VIEW],
        ])->assertForbidden()
            ->assertJsonPath('message', 'Permissions for this role cannot be modified.');

        $this->postJson("/api/v1/admin/roles/{$customer->id}/permissions/preview", [
            'add' => [AdminPermissions::ORDERS_VIEW],
        ])->assertForbidden()
            ->assertJsonPath('message', 'Permissions for this role cannot be modified.');
    }

    public function test_preview_matches_update_privilege_escalation_rejection(): void
    {
        $role = Role::query()->where('slug', 'warehouse_officer')->firstOrFail();

        $actor = Admin::factory()->withPermissions([
            AdminPermissions::ROLES_MANAGE_PERMISSIONS,
            AdminPermissions::ORDERS_VIEW,
        ])->create();

        Sanctum::actingAs($actor);

        $payload = ['add' => [AdminPermissions::PAYMENTS_REFUND]];

        $this->postJson("/api/v1/admin/roles/{$role->id}/permissions/preview", $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot grant a permission you do not hold: '.AdminPermissions::PAYMENTS_REFUND.'.');

        $this->patchJson("/api/v1/admin/roles/{$role->id}/permissions", $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot grant a permission you do not hold: '.AdminPermissions::PAYMENTS_REFUND.'.');
    }

    public function test_preview_matches_update_self_lockout_rejection(): void
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

        $payload = ['remove' => [AdminPermissions::ADMINS_VIEW]];

        $this->postJson("/api/v1/admin/roles/{$role->id}/permissions/preview", $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot remove admin access from your own role.');

        $this->patchJson("/api/v1/admin/roles/{$role->id}/permissions", $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot remove admin access from your own role.');
    }

    public function test_preview_matches_update_governance_path_rejection(): void
    {
        Admin::query()->update(['is_super_admin' => false, 'is_active' => true]);

        $viewId = Permission::query()->where('slug', AdminPermissions::ADMINS_VIEW)->value('id');
        $manageId = Permission::query()->where('slug', AdminPermissions::ROLES_MANAGE_PERMISSIONS)->value('id');

        $governanceRole = Role::factory()->create([
            'name' => 'Governance Only Preview',
            'slug' => 'governance_only_preview',
        ]);
        $governanceRole->permissions()->sync([$viewId, $manageId]);

        Role::query()
            ->whereNotIn('slug', ['administrator', 'customer', 'governance_only_preview'])
            ->each(function (Role $role) use ($viewId, $manageId) {
                $role->permissions()->detach([$viewId, $manageId]);
            });

        $operatorRole = Role::factory()->create([
            'name' => 'Permission Operator Preview',
            'slug' => 'permission_operator_preview',
        ]);
        $operatorRole->permissions()->sync([$manageId]);

        $actor = Admin::factory()->ordinary()->create([
            'role_id' => $operatorRole->id,
            'is_super_admin' => false,
        ]);

        Sanctum::actingAs($actor);

        $payload = ['remove' => [AdminPermissions::ROLES_MANAGE_PERMISSIONS]];
        $expectedMessage = 'At least one non-protected role must retain admin governance permissions when no active super admin exists.';

        $this->postJson("/api/v1/admin/roles/{$governanceRole->id}/permissions/preview", $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', $expectedMessage);

        $this->patchJson("/api/v1/admin/roles/{$governanceRole->id}/permissions", $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', $expectedMessage);
    }
}
