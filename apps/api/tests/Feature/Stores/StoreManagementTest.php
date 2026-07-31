<?php

namespace Tests\Feature\Stores;

use App\Enums\ActivityEventType;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Store;
use App\Models\User;
use App\Services\Stores\StoreAssignmentService;
use App\Services\Stores\StoreService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MinimalTestImage;
use Tests\TestCase;

class StoreManagementTest extends TestCase
{
    use RefreshDatabase;

    private function adminWith(array $permissions): Admin
    {
        return Admin::factory()->withPermissions($permissions)->create();
    }

    public function test_guest_and_customer_denied(): void
    {
        $store = app(StoreService::class)->create(['code' => 'ZION', 'name' => 'Zion']);

        $this->getJson('/api/v1/admin/stores')->assertUnauthorized();
        $this->postJson('/api/v1/admin/stores', ['code' => 'X', 'name' => 'X'])->assertUnauthorized();
        $this->getJson('/api/v1/admin/stores/'.$store->id)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/stores')->assertUnauthorized();
        $this->postJson('/api/v1/admin/stores', ['code' => 'X', 'name' => 'X'])->assertUnauthorized();
    }

    public function test_missing_permission_denied(): void
    {
        $store = app(StoreService::class)->create(['code' => 'ZION', 'name' => 'Zion']);
        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());

        $this->getJson('/api/v1/admin/stores')->assertForbidden();
        $this->postJson('/api/v1/admin/stores', [
            'code' => 'NEW1',
            'name' => 'New Store',
        ])->assertForbidden();
        $this->putJson('/api/v1/admin/stores/'.$store->id, [
            'name' => 'Renamed',
        ])->assertForbidden();
        $this->patchJson('/api/v1/admin/stores/'.$store->id.'/status', [
            'is_active' => false,
        ])->assertForbidden();
    }

    public function test_create_store_with_location_terminal_and_audit(): void
    {
        Sanctum::actingAs($this->adminWith([
            AdminPermissions::STORES_VIEW,
            AdminPermissions::STORES_CREATE,
        ]));

        $response = $this->postJson('/api/v1/admin/stores', [
            'code' => 'ROVI',
            'name' => 'Rovi Boutique',
            'slug' => 'rovi-boutique',
            'description' => 'Local fashion',
            'theme_color' => '#123456',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'ROVI')
            ->assertJsonPath('data.name', 'Rovi Boutique')
            ->assertJsonPath('data.slug', 'rovi-boutique')
            ->assertJsonPath('data.theme_color', '#123456');

        $storeId = $response->json('data.id');
        $this->assertDatabaseHas('inventory_locations', [
            'store_id' => $storeId,
            'code' => 'ROVI',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('pos_terminals', [
            'store_id' => $storeId,
            'code' => 'T1',
        ]);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::StoreCreated->value)
                ->where('subject_id', $storeId)
                ->exists(),
        );
    }

    public function test_update_store_renames_and_writes_audit(): void
    {
        $store = app(StoreService::class)->create([
            'code' => 'TZUR',
            'name' => 'Tzur',
            'slug' => 'tzur',
        ]);

        Sanctum::actingAs($this->adminWith([
            AdminPermissions::STORES_VIEW,
            AdminPermissions::STORES_UPDATE,
            AdminPermissions::STORES_CREATE,
        ]));

        $this->putJson('/api/v1/admin/stores/'.$store->id, [
            'name' => 'Tzur Renamed',
            'slug' => 'tzur-renamed',
            'description' => 'Updated desc',
            'theme_color' => '#abcdef',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Tzur Renamed')
            ->assertJsonPath('data.slug', 'tzur-renamed');

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::StoreUpdated->value)
                ->where('subject_id', $store->id)
                ->exists(),
        );
    }

    public function test_activate_deactivate_via_status_endpoint(): void
    {
        $store = app(StoreService::class)->create([
            'code' => 'PEACH',
            'name' => 'Peachy',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->adminWith([
            AdminPermissions::STORES_VIEW,
            AdminPermissions::STORES_UPDATE,
            AdminPermissions::STORES_CREATE,
        ]));

        $this->patchJson('/api/v1/admin/stores/'.$store->id.'/status', [
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::StoreDeactivated->value)
                ->where('subject_id', $store->id)
                ->exists(),
        );

        $this->patchJson('/api/v1/admin/stores/'.$store->id.'/status', [
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.is_active', true);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::StoreActivated->value)
                ->where('subject_id', $store->id)
                ->exists(),
        );
    }

    public function test_branding_upload_updates_paths_and_audit(): void
    {
        Storage::fake('public');

        $store = app(StoreService::class)->create([
            'code' => 'ZION',
            'name' => 'Zion',
        ]);

        Sanctum::actingAs($this->adminWith([
            AdminPermissions::STORES_VIEW,
            AdminPermissions::STORES_UPDATE,
            AdminPermissions::STORES_CREATE,
        ]));

        $this->post('/api/v1/admin/stores/'.$store->id.'/branding', [
            'logo' => MinimalTestImage::jpeg('logo.jpg'),
            'banner' => MinimalTestImage::jpeg('banner.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->assertOk()
            ->assertJsonStructure([
                'data' => ['logo_path', 'logo_url', 'banner_path', 'banner_url'],
            ]);

        $store->refresh();
        $this->assertNotNull($store->logo_path);
        $this->assertNotNull($store->banner_path);
        Storage::disk('public')->assertExists($store->logo_path);
        Storage::disk('public')->assertExists($store->banner_path);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::StoreBrandingUpdated->value)
                ->where('subject_id', $store->id)
                ->exists(),
        );
    }

    public function test_rejects_manual_logo_path_on_create_and_update(): void
    {
        $store = app(StoreService::class)->create(['code' => 'ZION', 'name' => 'Zion']);

        Sanctum::actingAs($this->adminWith([
            AdminPermissions::STORES_VIEW,
            AdminPermissions::STORES_CREATE,
            AdminPermissions::STORES_UPDATE,
        ]));

        $this->postJson('/api/v1/admin/stores', [
            'code' => 'BAD1',
            'name' => 'Bad',
            'logo_path' => 'stores/secret.png',
        ])->assertStatus(422)->assertJsonValidationErrors(['logo_path']);

        $this->putJson('/api/v1/admin/stores/'.$store->id, [
            'banner_path' => 'https://evil.example/banner.png',
        ])->assertStatus(422)->assertJsonValidationErrors(['banner_path']);
    }

    public function test_ownership_isolation_blocks_unassigned_store_manager(): void
    {
        $zion = app(StoreService::class)->create(['code' => 'ZION', 'name' => 'Zion']);
        $peachy = app(StoreService::class)->create(['code' => 'PEACHY', 'name' => 'Peachy']);

        $manager = $this->adminWith([
            AdminPermissions::STORES_VIEW,
            AdminPermissions::STORES_UPDATE,
            AdminPermissions::STORES_MANAGE,
        ]);
        $super = Admin::factory()->superAdmin()->create();
        app(StoreAssignmentService::class)->assign($manager, $zion, $super);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/admin/stores/'.$zion->id)->assertOk();
        $this->getJson('/api/v1/admin/stores/'.$peachy->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['store_id']);

        $this->putJson('/api/v1/admin/stores/'.$peachy->id, [
            'name' => 'Hijacked',
        ])->assertStatus(422)->assertJsonValidationErrors(['store_id']);

        $this->patchJson('/api/v1/admin/stores/'.$peachy->id.'/status', [
            'is_active' => false,
        ])->assertStatus(422)->assertJsonValidationErrors(['store_id']);
    }

    public function test_view_permission_can_list_but_not_create(): void
    {
        app(StoreService::class)->create(['code' => 'ZION', 'name' => 'Zion']);

        Sanctum::actingAs($this->adminWith([AdminPermissions::STORES_VIEW]));

        $this->getJson('/api/v1/admin/stores')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/v1/admin/stores', [
            'code' => 'NEW2',
            'name' => 'New Two',
        ])->assertForbidden();
    }
}
