<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\ChinaCommercialStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductPricingAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Sanctum::actingAs(Admin::factory()->create());
    }

    public function test_admin_can_create_china_simple_product_with_selling_and_cost_price(): void
    {
        $fixture = $this->chinaCatalogFixture();

        $created = $this->postJson('/api/v1/admin/products', [
            'name' => 'China Charger',
            'catalog_product_type_id' => $fixture['catalogType']->id,
            'commerce_channel_id' => $fixture['chinaChannelId'],
            'supplier_id' => $fixture['supplier']->id,
            'price' => 25000,
            'cost_price' => 18000,
            'status' => 'draft',
        ])->assertCreated();

        $productId = $created->json('data.id');

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'price' => '25000.00',
            'cost_price' => '18000.00',
        ]);

        $this->getJson('/api/v1/admin/products/'.$productId)
            ->assertOk()
            ->assertJsonPath('data.price', '25000.00')
            ->assertJsonPath('data.cost_price', '18000.00');
    }

    public function test_admin_can_edit_product_cost_price(): void
    {
        $product = Product::factory()->fromChina()->create([
            'price' => 30000,
            'cost_price' => 12000,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'name' => $product->name,
            'catalog_product_type_id' => $product->catalog_product_type_id,
            'cost_price' => 15000,
        ])->assertOk()
            ->assertJsonPath('data.cost_price', '15000.00');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'cost_price' => '15000.00',
        ]);
    }

    public function test_admin_can_edit_product_selling_price(): void
    {
        $product = Product::factory()->fromChina()->create([
            'price' => 30000,
            'cost_price' => 12000,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'name' => $product->name,
            'catalog_product_type_id' => $product->catalog_product_type_id,
            'price' => 35000,
        ])->assertOk()
            ->assertJsonPath('data.price', '35000.00');
    }

    public function test_admin_can_edit_china_variant_pricing_with_cost(): void
    {
        $product = Product::factory()->fromChina()->create([
            'price' => 0,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'CN-VAR-001',
            'is_active' => true,
        ]);

        $created = $this->postJson('/api/v1/admin/variants/'.$variant->id.'/prices', [
            'price_type' => VariantPriceType::Retail->value,
            'currency' => 'TZS',
            'amount' => 1500000,
            'cost_price' => 1000000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ])->assertCreated();

        $priceId = $created->json('data.id');

        $updated = $this->putJson('/api/v1/admin/prices/'.$priceId, [
            'amount' => 1600000,
            'cost_price' => 1100000,
        ])->assertOk();

        $this->assertSame(1600000.0, (float) $updated->json('data.amount'));
        $this->assertSame(1100000.0, (float) $updated->json('data.cost_price'));
    }

    public function test_admin_can_edit_china_commercial_stock_after_pricing_setup(): void
    {
        $product = Product::factory()->fromChina()->create([
            'price' => 25000,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_active' => true,
        ]);

        $this->patchJson('/api/v1/admin/products/'.$product->id.'/commercial-stock', [
            'available_quantity' => 8,
        ])->assertOk()
            ->assertJsonPath('data.available_quantity', 8);

        $this->assertDatabaseHas('china_commercial_stocks', [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'available_quantity' => 8,
        ]);
    }

    public function test_tz_local_product_stock_update_remains_unchanged(): void
    {
        $product = Product::factory()->tzLocal()->create([
            'price' => 9000,
            'cost_price' => 6000,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $this->patchJson('/api/v1/admin/products/'.$product->id.'/commercial-stock', [
            'available_quantity' => 5,
        ])->assertStatus(422);

        $this->patchJson('/api/v1/admin/products/'.$product->id.'/stock', [
            'stock_quantity' => 12,
        ])->assertOk();

        $this->assertDatabaseHas('inventory', [
            'product_id' => $product->id,
            'quantity' => 12,
        ]);

        $this->assertSame(0, ChinaCommercialStock::query()->where('product_id', $product->id)->count());

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 4,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $this->patchJson('/api/v1/admin/variants/'.$variant->id.'/commercial-stock', [
            'available_quantity' => 5,
        ])->assertStatus(422);
    }

    /**
     * @return array{
     *     catalogType: \App\Models\CatalogProductType,
     *     chinaChannelId: string,
     *     supplier: \App\Models\Supplier
     * }
     */
    private function chinaCatalogFixture(): array
    {
        $department = \App\Models\Department::factory()->create();
        $category = \App\Models\Category::factory()->forDepartment($department)->create(['parent_id' => null]);
        $subcategory = \App\Models\Category::factory()->forDepartment($department)->create([
            'parent_id' => $category->id,
        ]);
        $catalogType = \App\Models\CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
        ]);
        $chinaChannel = \App\Models\CommerceChannel::query()
            ->where('code', 'CHINA_IMPORT')
            ->firstOrFail();
        $supplier = \App\Models\Supplier::factory()->create();

        return [
            'catalogType' => $catalogType,
            'chinaChannelId' => $chinaChannel->id,
            'supplier' => $supplier,
        ];
    }
}
