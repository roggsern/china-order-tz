<?php

namespace Tests\Feature\Stores;

use App\Enums\ActivityEventType;
use App\Enums\StoreOperationalScope;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Store;
use App\Services\Stores\StoreService;
use App\Services\Stores\StoreTeamService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStoreTeamOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function adminWith(array $permissions): Admin
    {
        return Admin::factory()->withPermissions($permissions)->create();
    }

    private function store(string $code, string $name): Store
    {
        return app(StoreService::class)->create(['code' => $code, 'name' => $name]);
    }

    public function test_super_admin_can_assign_and_remove_team_member(): void
    {
        $store = $this->store('ZION', 'Zion');
        $member = Admin::factory()->create();
        $super = Admin::factory()->superAdmin()->create();

        Sanctum::actingAs($super);

        $this->postJson('/api/v1/admin/stores/'.$store->id.'/team', [
            'admin_id' => $member->id,
            'operational_scope' => StoreOperationalScope::StoreManager->value,
        ])->assertCreated()
            ->assertJsonPath('data.operational_scope', StoreOperationalScope::StoreManager->value);

        $this->assertDatabaseHas('store_user_assignments', [
            'store_id' => $store->id,
            'admin_id' => $member->id,
            'operational_scope' => StoreOperationalScope::StoreManager->value,
            'is_active' => true,
        ]);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::StoreTeamAssigned->value)
                ->where('subject_id', $store->id)
                ->exists(),
        );

        $this->deleteJson('/api/v1/admin/stores/'.$store->id.'/team/'.$member->id)
            ->assertOk();

        $this->assertDatabaseHas('store_user_assignments', [
            'store_id' => $store->id,
            'admin_id' => $member->id,
            'is_active' => false,
        ]);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::StoreTeamRemoved->value)
                ->where('subject_id', $store->id)
                ->exists(),
        );
    }

    public function test_store_manager_can_manage_team_on_assigned_store_only(): void
    {
        $zion = $this->store('ZION', 'Zion');
        $peachy = $this->store('PEACHY', 'Peachy');
        $manager = $this->adminWith([
            AdminPermissions::STORES_VIEW,
            AdminPermissions::STORES_TEAM_VIEW,
            AdminPermissions::STORES_TEAM_MANAGE,
        ]);
        $operator = Admin::factory()->create();
        $super = Admin::factory()->superAdmin()->create();

        app(StoreTeamService::class)->assign(
            $manager,
            $zion,
            $super,
            StoreOperationalScope::StoreManager,
        );

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/admin/stores/'.$zion->id.'/team')->assertOk();
        $this->getJson('/api/v1/admin/stores/'.$peachy->id.'/team')->assertForbidden();

        $this->postJson('/api/v1/admin/stores/'.$zion->id.'/team', [
            'admin_id' => $operator->id,
            'operational_scope' => StoreOperationalScope::StoreOperator->value,
        ])->assertCreated();

        $this->putJson('/api/v1/admin/stores/'.$zion->id.'/team/'.$operator->id, [
            'operational_scope' => StoreOperationalScope::StoreViewer->value,
        ])->assertOk()
            ->assertJsonPath('data.operational_scope', StoreOperationalScope::StoreViewer->value);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::StoreTeamUpdated->value)
                ->where('subject_id', $zion->id)
                ->exists(),
        );
    }

    public function test_store_viewer_cannot_manage_store(): void
    {
        $store = $this->store('ZION', 'Zion');
        $viewer = $this->adminWith([
            AdminPermissions::STORES_VIEW,
            AdminPermissions::STORES_UPDATE,
        ]);
        $super = Admin::factory()->superAdmin()->create();

        app(StoreTeamService::class)->assign(
            $viewer,
            $store,
            $super,
            StoreOperationalScope::StoreViewer,
        );

        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/admin/stores/'.$store->id)->assertOk();

        $this->putJson('/api/v1/admin/stores/'.$store->id, [
            'name' => 'Renamed by viewer',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['store_id']);
    }

    public function test_my_stores_returns_assignments(): void
    {
        $zion = $this->store('ZION', 'Zion');
        $peachy = $this->store('PEACHY', 'Peachy');
        $manager = $this->adminWith([AdminPermissions::STORES_VIEW]);
        $super = Admin::factory()->superAdmin()->create();

        app(StoreTeamService::class)->assign(
            $manager,
            $zion,
            $super,
            StoreOperationalScope::StoreManager,
        );

        Sanctum::actingAs($manager);

        $ids = collect($this->getJson('/api/v1/admin/my-stores')->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($zion->id));
        $this->assertFalse($ids->contains($peachy->id));
    }

    public function test_store_dashboard_requires_store_access(): void
    {
        $zion = $this->store('ZION', 'Zion');
        $peachy = $this->store('PEACHY', 'Peachy');
        $manager = $this->adminWith([AdminPermissions::STORES_VIEW]);
        $super = Admin::factory()->superAdmin()->create();

        app(StoreTeamService::class)->assign(
            $manager,
            $zion,
            $super,
            StoreOperationalScope::StoreManager,
        );

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/admin/stores/'.$zion->id.'/dashboard')
            ->assertOk()
            ->assertJsonPath('data.store.id', $zion->id)
            ->assertJsonStructure([
                'data' => [
                    'sales_summary',
                    'profit_summary',
                    'top_products',
                    'customers',
                ],
            ]);

        $this->getJson('/api/v1/admin/stores/'.$peachy->id.'/dashboard')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['store_id']);
    }

    public function test_unassigned_admin_forbidden_from_team(): void
    {
        $store = $this->store('ZION', 'Zion');
        Sanctum::actingAs($this->adminWith([
            AdminPermissions::STORES_VIEW,
        ]));

        $this->getJson('/api/v1/admin/stores/'.$store->id.'/team')->assertForbidden();
    }
}
