<?php

namespace Tests\Feature\Admin;

use App\Enums\CatalogOrigin;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Department;
use App\Models\Store;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTzStoreCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_VIEW,
                AdminPermissions::CATALOG_CREATE,
                AdminPermissions::CATALOG_UPDATE,
                AdminPermissions::CATALOG_DELETE,
                AdminPermissions::STORES_VIEW,
            ])->create(),
        );
    }

    public function test_tz_category_can_be_created_without_department(): void
    {
        $store = $this->makeStore('ZION MODE', 'ZION');

        $response = $this->postJson('/api/v1/admin/categories', [
            'name' => 'Tops',
            'origin' => CatalogOrigin::Tz->value,
            'store_id' => $store->id,
            'department_id' => null,
            'is_active' => true,
            'sort_order' => 1,
        ])->assertCreated();

        $this->assertNull($response->json('data.department_id'));
        $this->assertSame($store->id, $response->json('data.store_id'));
        $this->assertSame(CatalogOrigin::Tz->value, $response->json('data.origin'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Tops',
            'store_id' => $store->id,
            'origin' => CatalogOrigin::Tz->value,
            'department_id' => null,
            'is_active' => true,
        ]);
    }

    public function test_tz_category_requires_store(): void
    {
        $this->postJson('/api/v1/admin/categories', [
            'name' => 'Tops',
            'origin' => CatalogOrigin::Tz->value,
            'is_active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['store_id']);
    }

    public function test_china_category_still_requires_department(): void
    {
        $this->postJson('/api/v1/admin/categories', [
            'name' => 'Phones',
            'origin' => CatalogOrigin::China->value,
            'is_active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id']);
    }

    public function test_china_category_rejects_store_id(): void
    {
        $store = $this->makeStore('ZION MODE', 'ZION');
        $department = Department::factory()->create();

        $this->postJson('/api/v1/admin/categories', [
            'name' => 'Phones',
            'origin' => CatalogOrigin::China->value,
            'department_id' => $department->id,
            'store_id' => $store->id,
            'is_active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['store_id']);
    }

    public function test_store_scoped_list_and_active_filter(): void
    {
        $zion = $this->makeStore('ZION MODE', 'ZION');
        $rovi = $this->makeStore('ROVI BEAUTY', 'ROVI');

        $tops = Category::factory()->forStore($zion)->create([
            'name' => 'Tops',
            'is_active' => true,
            'parent_id' => null,
        ]);
        Category::factory()->forStore($zion)->create([
            'name' => 'Archived Line',
            'is_active' => false,
            'parent_id' => null,
        ]);
        Category::factory()->forStore($rovi)->create([
            'name' => 'Wigs',
            'is_active' => true,
            'parent_id' => null,
        ]);

        $zionActive = $this->getJson(
            '/api/v1/admin/categories?origin=tz&store_id='.$zion->id.'&is_active=1&per_page=100',
        )->assertOk()->json('data');

        $names = collect($zionActive)->pluck('name')->all();
        $this->assertContains('Tops', $names);
        $this->assertNotContains('Archived Line', $names);
        $this->assertNotContains('Wigs', $names);
        $this->assertTrue(
            collect($zionActive)->every(fn (array $row) => ($row['store_id'] ?? null) === $zion->id),
        );

        $this->assertSame(
            $tops->id,
            collect($zionActive)->firstWhere('name', 'Tops')['id'] ?? null,
        );
    }

    public function test_tz_subcategory_inherits_store_and_origin(): void
    {
        $zion = $this->makeStore('ZION MODE', 'ZION');
        $tops = Category::factory()->forStore($zion)->create([
            'name' => 'Tops',
            'parent_id' => null,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/subcategories', [
            'name' => 'Blouses',
            'category_id' => $tops->id,
            'is_active' => true,
        ])->assertCreated();

        $this->assertSame($zion->id, $response->json('data.store_id'));
        $this->assertSame(CatalogOrigin::Tz->value, $response->json('data.origin'));
        $this->assertSame($tops->id, $response->json('data.category_id'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Blouses',
            'parent_id' => $tops->id,
            'store_id' => $zion->id,
            'origin' => CatalogOrigin::Tz->value,
            'department_id' => null,
        ]);
    }

    public function test_tz_subcategory_list_can_filter_by_store(): void
    {
        $zion = $this->makeStore('ZION MODE', 'ZION');
        $rovi = $this->makeStore('ROVI BEAUTY', 'ROVI');
        $tops = Category::factory()->forStore($zion)->create(['name' => 'Tops', 'parent_id' => null]);
        $wigs = Category::factory()->forStore($rovi)->create(['name' => 'Wigs', 'parent_id' => null]);

        Category::factory()->forStore($zion)->child($tops)->create(['name' => 'Blouses']);
        Category::factory()->forStore($rovi)->child($wigs)->create(['name' => 'Lace Wigs']);

        $rows = $this->getJson(
            '/api/v1/admin/subcategories?origin=tz&store_id='.$zion->id.'&per_page=100',
        )->assertOk()->json('data');

        $names = collect($rows)->pluck('name')->all();
        $this->assertContains('Blouses', $names);
        $this->assertNotContains('Lace Wigs', $names);
    }

    public function test_catalog_view_without_create_cannot_create_tz_category(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_VIEW,
            ])->create(),
        );

        $store = $this->makeStore('ZION MODE', 'ZION');

        $this->postJson('/api/v1/admin/categories', [
            'name' => 'Tops',
            'origin' => CatalogOrigin::Tz->value,
            'store_id' => $store->id,
        ])->assertForbidden();
    }

    private function makeStore(string $name, string $code): Store
    {
        return Store::query()->create([
            'code' => $code,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'is_active' => true,
        ]);
    }
}
