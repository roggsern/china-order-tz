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
use Database\Seeders\CoreDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Guards CreateProductAction commerce-channel resolution for both journeys.
 */
class CreateProductActionChannelResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreDatabaseSeeder::class);
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::CATALOG_CREATE])->create(),
        );
    }

    public function test_china_import_product_creation_with_department_category(): void
    {
        $department = Department::factory()->create();
        $root = Category::factory()->forDepartment($department)->china()->create(['parent_id' => null]);
        $leaf = Category::factory()->forDepartment($department)->child($root)->create([
            'name' => 'Android Phones',
            'slug' => 'china-channel-android-phones',
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $leaf->id,
            'name' => 'Android Smartphone',
            'slug' => 'china-channel-android-smartphone',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/products', [
            'name' => 'China Channel Phone',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'supplier_id' => Supplier::factory()->create(['is_active' => true, 'country' => 'CN'])->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 250000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'China Channel Phone');

        $product = Product::query()->where('name', 'China Channel Phone')->first();
        $this->assertNotNull($product);
        $this->assertNull($product->store_id);
        $this->assertSame($leaf->id, $product->category_id);
        $this->assertSame(CatalogOrigin::China, $product->category?->resolvedOrigin());
    }

    public function test_tz_local_product_creation_with_store_category(): void
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
            'name' => 'TZ Channel Wig',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'TZ_LOCAL')->value('id'),
            'store_id' => $store->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 120000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'TZ Channel Wig')
            ->assertJsonPath('data.store_id', $store->id);

        $product = Product::query()->where('name', 'TZ Channel Wig')->first();
        $this->assertNotNull($product);
        $this->assertSame($wigsCategory->id, $product->category_id);
        $this->assertSame(CatalogOrigin::Tz, $product->category?->resolvedOrigin());
    }
}
