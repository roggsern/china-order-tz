<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Role;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
    }

    public function test_super_admin_can_crud_admin_accounts_and_emit_audit_events(): void
    {
        $actor = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($actor);

        $warehouseRole = Role::query()->where('slug', 'warehouse_officer')->firstOrFail();

        $created = $this->postJson('/api/v1/admin/admins', [
            'name' => 'Warehouse Lead',
            'email' => 'warehouse.lead@example.com',
            'phone' => '0712345678',
            'password' => 'password123',
            'role_id' => $warehouseRole->id,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'warehouse.lead@example.com')
            ->assertJsonPath('data.is_super_admin', false)
            ->assertJsonPath('data.role.slug', 'warehouse_officer');

        $adminId = $created->json('data.id');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::AdminCreated->value,
            'subject_id' => $adminId,
            'actor_id' => $actor->id,
        ]);

        $this->getJson('/api/v1/admin/admins')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson("/api/v1/admin/admins/{$adminId}")
            ->assertOk()
            ->assertJsonPath('data.id', $adminId);

        $this->patchJson("/api/v1/admin/admins/{$adminId}", [
            'name' => 'Warehouse Lead Updated',
            'phone' => '0798765432',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Warehouse Lead Updated')
            ->assertJsonPath('data.phone', '0798765432');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::AdminUpdated->value,
            'subject_id' => $adminId,
        ]);
    }

    public function test_admin_endpoints_require_granular_permissions(): void
    {
        $targetRole = Role::query()->where('slug', 'warehouse_officer')->firstOrFail();
        $target = Admin::factory()->ordinary()->create([
            'role_id' => $targetRole->id,
            'email' => 'target@example.com',
        ]);

        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());
        $this->getJson('/api/v1/admin/admins')->assertForbidden();
        $this->postJson('/api/v1/admin/admins', [
            'name' => 'Blocked',
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'role_id' => $targetRole->id,
        ])->assertForbidden();
        $this->getJson("/api/v1/admin/admins/{$target->id}")->assertForbidden();
        $this->patchJson("/api/v1/admin/admins/{$target->id}", ['name' => 'Nope'])->assertForbidden();
        $this->patchJson("/api/v1/admin/admins/{$target->id}/activate")->assertForbidden();
        $this->patchJson("/api/v1/admin/admins/{$target->id}/deactivate")->assertForbidden();
        $this->patchJson("/api/v1/admin/admins/{$target->id}/role", [
            'role_id' => $targetRole->id,
        ])->assertForbidden();

        $viewer = Admin::factory()->withPermissions([AdminPermissions::ADMINS_VIEW])->create();
        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/admin/admins')->assertOk();
        $this->getJson("/api/v1/admin/admins/{$target->id}")->assertOk();
        $this->postJson('/api/v1/admin/admins', [
            'name' => 'Blocked',
            'email' => 'blocked2@example.com',
            'password' => 'password123',
            'role_id' => $targetRole->id,
        ])->assertForbidden();
    }

    public function test_activate_deactivate_and_role_assignment_emit_audit_events(): void
    {
        $actor = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($actor);

        $warehouseRole = Role::query()->where('slug', 'warehouse_officer')->firstOrFail();
        $managerRole = Role::query()->where('slug', 'manager')->firstOrFail();

        $target = Admin::factory()->ordinary()->create([
            'role_id' => $warehouseRole->id,
            'is_active' => true,
        ]);

        $this->patchJson("/api/v1/admin/admins/{$target->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::AdminDeactivated->value,
            'subject_id' => $target->id,
        ]);

        $this->patchJson("/api/v1/admin/admins/{$target->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::AdminActivated->value,
            'subject_id' => $target->id,
        ]);

        $this->patchJson("/api/v1/admin/admins/{$target->id}/role", [
            'role_id' => $managerRole->id,
        ])->assertOk()
            ->assertJsonPath('data.role.slug', 'manager');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::AdminRoleAssigned->value,
            'subject_id' => $target->id,
        ]);
    }

    public function test_non_super_admin_cannot_manage_super_admin_accounts(): void
    {
        $manager = Admin::factory()->withPermissions([
            AdminPermissions::ADMINS_VIEW,
            AdminPermissions::ADMINS_UPDATE,
        ])->create();

        $superTarget = Admin::factory()->superAdmin()->create([
            'email' => 'super.target@example.com',
        ]);

        Sanctum::actingAs($manager);
        $this->patchJson("/api/v1/admin/admins/{$superTarget->id}", [
            'name' => 'Blocked rename',
        ])->assertForbidden()
            ->assertJsonPath('message', 'You cannot manage super admin accounts.');
    }

    public function test_non_super_admin_cannot_assign_administrator_role(): void
    {
        $actor = Admin::factory()->withPermissions([
            AdminPermissions::ADMINS_CREATE,
            AdminPermissions::ADMINS_ASSIGN_ROLES,
        ])->create();

        $warehouseRole = Role::query()->where('slug', 'warehouse_officer')->firstOrFail();
        $administratorRole = Role::query()->where('slug', 'administrator')->firstOrFail();
        $target = Admin::factory()->ordinary()->create(['role_id' => $warehouseRole->id]);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/admin/admins', [
            'name' => 'Should Fail',
            'email' => 'fail.admin@example.com',
            'password' => 'password123',
            'role_id' => $administratorRole->id,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Only super admins can assign the administrator role.');

        $this->patchJson("/api/v1/admin/admins/{$target->id}/role", [
            'role_id' => $administratorRole->id,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Only super admins can assign the administrator role.');
    }

    public function test_admin_cannot_deactivate_self_or_last_active_super_admin(): void
    {
        $superAdmin = Admin::factory()->superAdmin()->create([
            'email' => 'only.super@example.com',
        ]);
        Sanctum::actingAs($superAdmin);

        $this->patchJson("/api/v1/admin/admins/{$superAdmin->id}/deactivate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot deactivate your own account.');

        Admin::query()->where('is_super_admin', true)
            ->where('id', '!=', $superAdmin->id)
            ->update(['is_active' => false]);

        $this->patchJson("/api/v1/admin/admins/{$superAdmin->id}/deactivate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot deactivate your own account.');
    }

    public function test_non_super_admin_cannot_change_own_role(): void
    {
        $warehouseRole = Role::query()->where('slug', 'warehouse_officer')->firstOrFail();
        $managerRole = Role::query()->where('slug', 'manager')->firstOrFail();

        $actor = Admin::factory()->withPermissions([
            AdminPermissions::ADMINS_ASSIGN_ROLES,
        ])->create([
            'role_id' => $warehouseRole->id,
        ]);

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/admin/admins/{$actor->id}/role", [
            'role_id' => $managerRole->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'You cannot change your own role.');
    }

    public function test_assignable_roles_exclude_customer_and_administrator_for_non_super_admin(): void
    {
        $actor = Admin::factory()->withPermissions([AdminPermissions::ADMINS_VIEW])->create();
        Sanctum::actingAs($actor);

        $response = $this->getJson('/api/v1/admin/roles?assignable=1')->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertNotContains('customer', $slugs);
        $this->assertNotContains('administrator', $slugs);
        $this->assertContains('warehouse_officer', $slugs);
    }

    public function test_create_admin_never_sets_super_admin_flag(): void
    {
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $warehouseRole = Role::query()->where('slug', 'warehouse_officer')->firstOrFail();

        $this->postJson('/api/v1/admin/admins', [
            'name' => 'Regular Admin',
            'email' => 'regular.admin@example.com',
            'password' => 'password123',
            'role_id' => $warehouseRole->id,
            'is_super_admin' => true,
        ])->assertCreated()
            ->assertJsonPath('data.is_super_admin', false);

        $this->assertDatabaseHas('admins', [
            'email' => 'regular.admin@example.com',
            'is_super_admin' => false,
        ]);
    }
}
