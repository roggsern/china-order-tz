<?php

namespace Tests\Feature\Admin;

use App\Enums\CatalogOrigin;
use App\Enums\ProductLifecycleStatus;
use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Product;
use App\Models\Store;
use App\Models\Supplier;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_CREATE,
                AdminPermissions::CATALOG_VIEW,
            ])->create(),
        );
    }

    public function test_tz_local_rovi_beauty_can_create_wigs_product(): void
    {
        $fixture = $this->tzStoreFixture('ROVI BEAUTY', 'ROVI');

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Silk Wig',
            'catalog_product_type_id' => $fixture['catalogType']->id,
            'commerce_channel_id' => $fixture['tzChannelId'],
            'store_id' => $fixture['store']->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 85000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.store_id', $fixture['store']->id);

        $this->assertDatabaseHas('products', [
            'name' => 'Silk Wig',
            'store_id' => $fixture['store']->id,
            'category_id' => $fixture['subcategory']->id,
        ]);
    }

    public function test_tz_category_list_is_scoped_to_selected_store(): void
    {
        $rovi = $this->tzStoreFixture('ROVI BEAUTY', 'ROVI');
        $this->tzStoreFixture('Other Beauty', 'OTHER', 'Skin Care');

        $response = $this->getJson('/api/v1/admin/categories?origin=tz&store_id='.$rovi['store']->id)
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Wigs', $names);
        $this->assertNotContains('Skin Care', $names);
    }

    public function test_china_import_product_rejects_store_owned_category(): void
    {
        $tz = $this->tzStoreFixture('ROVI BEAUTY', 'ROVI');
        $china = $this->chinaCatalogFixture();

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Cross Channel Product',
            'catalog_product_type_id' => $tz['catalogType']->id,
            'commerce_channel_id' => $china['chinaChannelId'],
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_tz_local_product_rejects_china_department_category(): void
    {
        $china = $this->chinaCatalogFixture();
        $store = Store::query()->create([
            'code' => 'TZX1',
            'name' => 'TZ Store',
            'slug' => 'tz-store',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Wrong Taxonomy TZ',
            'catalog_product_type_id' => $china['catalogType']->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'TZ_LOCAL')->value('id'),
            'store_id' => $store->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_tz_local_product_rejects_category_from_different_store(): void
    {
        $rovi = $this->tzStoreFixture('ROVI BEAUTY', 'ROVI');
        $other = $this->tzStoreFixture('Other Beauty', 'OTHER');

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Mismatched Store Product',
            'catalog_product_type_id' => $other['catalogType']->id,
            'commerce_channel_id' => $rovi['tzChannelId'],
            'store_id' => $rovi['store']->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_china_import_product_creation_still_uses_department_taxonomy(): void
    {
        $china = $this->chinaCatalogFixture();

        $this->postJson('/api/v1/admin/products', [
            'name' => 'China Gadget',
            'catalog_product_type_id' => $china['catalogType']->id,
            'commerce_channel_id' => $china['chinaChannelId'],
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 120000,
        ])
            ->assertCreated();

        $product = Product::query()->where('name', 'China Gadget')->first();
        $this->assertNotNull($product);
        $this->assertNull($product->store_id);
        $this->assertSame(CatalogOrigin::China, $product->category?->resolvedOrigin());
    }

    /**
     * @return array{
     *     store: Store,
     *     rootCategory: Category,
     *     subcategory: Category,
     *     catalogType: CatalogProductType,
     *     tzChannelId: string
     * }
     */
    private function tzStoreFixture(string $storeName, string $storeCode, string $rootCategoryName = 'Wigs'): array
    {
        $store = Store::query()->create([
            'code' => $storeCode,
            'name' => $storeName,
            'slug' => str($storeName)->slug()->toString(),
            'is_active' => true,
        ]);

        $rootCategory = Category::factory()->forStore($store)->create([
            'name' => $rootCategoryName,
            'parent_id' => null,
        ]);
        $subcategory = Category::factory()->forStore($store)->child($rootCategory)->create([
            'name' => 'Human Hair Wigs',
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        return [
            'store' => $store,
            'rootCategory' => $rootCategory,
            'subcategory' => $subcategory,
            'catalogType' => $catalogType,
            'tzChannelId' => (string) CommerceChannel::query()->where('code', 'TZ_LOCAL')->value('id'),
        ];
    }

    /**
     * @return array{
     *     catalogType: CatalogProductType,
     *     chinaChannelId: string
     * }
     */
    private function chinaCatalogFixture(): array
    {
        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->china()->create(['parent_id' => null]);
        $subcategory = Category::factory()->forDepartment($department)->child($category)->create();
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        return [
            'catalogType' => $catalogType,
            'chinaChannelId' => (string) CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
        ];
    }
}
