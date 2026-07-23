<?php

namespace Tests\Feature\Admin;

use App\Enums\CatalogOrigin;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductType;
use Database\Seeders\ProductTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductCatalogEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_product_auto_generates_sku_and_derives_product_type(): void
    {
        $this->seed(ProductTypeSeeder::class);
        Sanctum::actingAs(Admin::factory()->create());

        $type = ProductType::query()->where('slug', 'phones')->firstOrFail();

        $root = Category::factory()->create([
            'origin' => CatalogOrigin::China,
            'product_type_id' => $type->id,
            'name' => 'Electronics',
            'slug' => 'electronics-root-engine',
        ]);

        $leaf = Category::factory()->child($root)->create([
            'origin' => CatalogOrigin::China,
            'name' => 'Smartphones',
            'slug' => 'electronics-smartphones-engine',
        ]);

        $cpt = \App\Models\CatalogProductType::factory()->create([
            'subcategory_id' => $leaf->id,
            'name' => 'Engine CPT Phones',
            'slug' => 'engine-cpt-phones',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/products', [
            'name' => 'Auto SKU Phone',
            'category_id' => $leaf->id,
            'catalog_product_type_id' => $cpt->id,
            'price' => 100000,
            'status' => true,
            'stock_quantity' => 5,
            'air_shipping_price' => 1000,
            'sea_shipping_price' => 500,
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $product = Product::query()->where('name', 'Auto SKU Phone')->first();
        $this->assertNotNull($product);
        $this->assertNotEmpty($product->sku);
        $this->assertSame($type->id, $product->product_type_id);
        $this->assertStringContainsString('COT-CN', $product->sku);
    }

    public function test_admin_categories_filter_by_origin_and_parent(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $chinaRoot = Category::factory()->create([
            'origin' => CatalogOrigin::China,
            'name' => 'China Root',
            'slug' => 'china-root-engine',
        ]);
        Category::factory()->child($chinaRoot)->create([
            'origin' => CatalogOrigin::China,
            'name' => 'China Child',
            'slug' => 'china-child-engine',
        ]);
        Category::factory()->create([
            'origin' => CatalogOrigin::Tz,
            'name' => 'TZ Root',
            'slug' => 'tz-root-engine',
        ]);

        $this->getJson('/api/v1/admin/categories?origin=china&roots_only=1&per_page=100')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $chinaRoot->id);

        $this->getJson('/api/v1/admin/categories?parent_id='.$chinaRoot->id.'&per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'china-child-engine');
    }
}
