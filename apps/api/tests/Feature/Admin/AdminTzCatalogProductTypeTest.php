<?php

namespace Tests\Feature\Admin;

use App\Enums\CatalogOrigin;
use App\Enums\ProductLifecycleStatus;
use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Store;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\CoreDatabaseSeeder;
use Database\Seeders\TzCatalogProductTypeSeeder;
use Database\Seeders\TzStoreCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTzCatalogProductTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreDatabaseSeeder::class);
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_CREATE,
                AdminPermissions::CATALOG_VIEW,
            ])->create(),
        );
    }

    public function test_rovi_beauty_wigs_category_has_wigs_catalog_product_type(): void
    {
        $store = Store::query()->where('slug', 'rovi-beauty')->firstOrFail();
        $wigsCategory = Category::query()
            ->where('store_id', $store->id)
            ->where('slug', 'rovi-beauty-wigs')
            ->firstOrFail();

        $catalogType = CatalogProductType::query()
            ->where('subcategory_id', $wigsCategory->id)
            ->where('name', 'Wigs')
            ->first();

        $this->assertNotNull($catalogType);
        $this->assertTrue($catalogType->is_active);
    }

    public function test_rovi_beauty_category_mappings_use_canonical_type_names(): void
    {
        $store = Store::query()->where('slug', 'rovi-beauty')->firstOrFail();

        $expected = [
            'rovi-beauty-wigs' => 'Wigs',
            'rovi-beauty-skincare' => 'Skin Care',
            'rovi-beauty-lotions' => 'Body Lotion',
            'rovi-beauty-makeup' => 'Makeup',
            'rovi-beauty-beauty-accessories' => 'Beauty Accessories',
        ];

        foreach ($expected as $slug => $typeName) {
            $category = Category::query()->where('store_id', $store->id)->where('slug', $slug)->first();
            $this->assertNotNull($category, "Missing category {$slug}");

            $this->assertDatabaseHas('catalog_product_types', [
                'subcategory_id' => $category->id,
                'name' => $typeName,
            ]);
        }
    }

    public function test_can_create_tz_product_with_rovi_wigs_catalog_product_type(): void
    {
        $store = Store::query()->where('slug', 'rovi-beauty')->firstOrFail();
        $wigsCategory = Category::query()
            ->where('store_id', $store->id)
            ->where('slug', 'rovi-beauty-wigs')
            ->firstOrFail();
        $catalogType = CatalogProductType::query()
            ->where('subcategory_id', $wigsCategory->id)
            ->where('name', 'Wigs')
            ->firstOrFail();

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Silky Lace Wig',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'TZ_LOCAL')->value('id'),
            'store_id' => $store->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 95000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.catalog_product_type_id', $catalogType->id);

        $this->assertDatabaseHas('products', [
            'name' => 'Silky Lace Wig',
            'store_id' => $store->id,
            'category_id' => $wigsCategory->id,
            'catalog_product_type_id' => $catalogType->id,
        ]);
    }

    public function test_tz_product_rejects_catalog_type_from_different_store(): void
    {
        $rovi = Store::query()->where('slug', 'rovi-beauty')->firstOrFail();
        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();

        $zionCategory = Category::query()
            ->where('store_id', $zion->id)
            ->where('origin', CatalogOrigin::Tz)
            ->firstOrFail();
        $zionCatalogType = CatalogProductType::query()
            ->where('subcategory_id', $zionCategory->id)
            ->firstOrFail();

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Mismatched Store Product',
            'catalog_product_type_id' => $zionCatalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'TZ_LOCAL')->value('id'),
            'store_id' => $rovi->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_china_catalog_product_types_remain_seeded(): void
    {
        $chinaTypeCount = CatalogProductType::query()
            ->whereHas('subcategory', fn ($query) => $query->where('origin', CatalogOrigin::China))
            ->count();

        $this->assertGreaterThan(0, $chinaTypeCount);

        $mensTshirt = CatalogProductType::query()
            ->where('slug', 'like', '%round-neck-t-shirt%')
            ->whereHas('subcategory.department', fn ($query) => $query->where('slug', 'mens-fashion'))
            ->first();

        $this->assertNotNull($mensTshirt);
    }

    public function test_tz_catalog_type_seeder_is_idempotent(): void
    {
        $before = CatalogProductType::query()
            ->whereHas('subcategory', fn ($query) => $query->where('origin', CatalogOrigin::Tz))
            ->count();

        $this->seed(TzStoreCategorySeeder::class);
        $this->seed(TzCatalogProductTypeSeeder::class);

        $after = CatalogProductType::query()
            ->whereHas('subcategory', fn ($query) => $query->where('origin', CatalogOrigin::Tz))
            ->count();

        $this->assertSame($before, $after);
    }
}
