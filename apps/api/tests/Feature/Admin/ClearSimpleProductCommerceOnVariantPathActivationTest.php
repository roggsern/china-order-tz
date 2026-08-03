<?php

namespace Tests\Feature\Admin;

use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClearSimpleProductCommerceOnVariantPathActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_sellable_variant_clears_simple_product_price_and_stock(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->tzLocal()->create([
            'name' => 'Variant Path Cleanup',
            'slug' => 'variant-path-cleanup',
            'price' => 45000,
            'cost_price' => 30000,
        ]);

        Inventory::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 12,
            'reserved_quantity' => 0,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Standard',
            'sku' => 'VPC-STD',
            'price' => null,
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->postJson('/api/v1/admin/variants/'.$variant->id.'/prices', [
            'price_type' => VariantPriceType::Retail->value,
            'currency' => 'TZS',
            'amount' => 38000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/variants/'.$variant->id.'/inventory', [
            'warehouse_code' => 'main',
            'on_hand' => 8,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ])->assertCreated();

        $product->refresh();
        $simpleInventory = Inventory::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->first();

        $this->assertSame('0.00', (string) $product->price);
        $this->assertNull($product->cost_price);
        $this->assertNotNull($simpleInventory);
        $this->assertSame(0, (int) $simpleInventory->quantity);
    }

    public function test_simple_product_publish_flow_remains_available(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::CATALOG_CREATE])->create(),
        );

        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Simple Wizard Product',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'lifecycle_status' => 'draft',
            'price' => 12000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.price', '12000.00');

        $this->assertDatabaseHas('products', [
            'name' => 'Simple Wizard Product',
            'price' => 12000,
        ]);
    }

    public function test_china_variant_draft_can_be_created_with_zero_simple_price(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::CATALOG_CREATE])->create(),
        );

        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/products', [
            'name' => 'China Variant Draft',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'lifecycle_status' => 'draft',
            'price' => 0,
        ])->assertCreated();

        $this->assertDatabaseHas('products', [
            'name' => 'China Variant Draft',
            'price' => 0,
        ]);
    }

    public function test_tz_variant_draft_can_be_created_with_store_and_zero_simple_price(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::CATALOG_CREATE])->create(),
        );

        $store = Store::query()->create([
            'code' => 'VINT',
            'name' => 'Variant Intent Store',
            'slug' => 'variant-intent-store',
            'is_active' => true,
        ]);
        $root = Category::factory()->forStore($store)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forStore($store)->child($root)->create();
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/products', [
            'name' => 'TZ Variant Draft',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'TZ_LOCAL')->value('id'),
            'store_id' => $store->id,
            'lifecycle_status' => 'draft',
            'price' => 0,
        ])->assertCreated();

        $product = Product::query()->where('name', 'TZ Variant Draft')->first();
        $this->assertNotNull($product);
        $this->assertSame($store->id, $product->store_id);
        $this->assertSame('0.00', (string) $product->price);
    }
}
