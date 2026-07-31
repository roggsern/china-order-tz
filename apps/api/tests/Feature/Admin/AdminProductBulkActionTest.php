<?php

namespace Tests\Feature\Admin;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
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
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductBulkActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_bulk_publish_and_archive_partial_success(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $ready = $this->makePublishableTzProduct([
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
            'is_demo' => false,
        ]);

        $demo = Product::factory()->fromChina()->create([
            'name' => 'Bulk Demo Phone',
            'slug' => 'bulk-demo-phone',
            'price' => 40000,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
            'is_demo' => true,
        ]);

        $publish = $this->postJson('/api/v1/admin/products/bulk-action', [
            'action_key' => 'publish',
            'product_ids' => [$ready->id, $demo->id, '019f7a6e-4d46-7376-aca4-000000000099'],
        ]);

        $publish->assertOk()->assertJsonPath('data.total', 3);

        $results = collect($publish->json('data.results'));
        $this->assertTrue((bool) $results->firstWhere('product_id', $ready->id)['success']);
        $this->assertFalse((bool) $results->firstWhere('product_id', $demo->id)['success']);
        $this->assertFalse((bool) $results->firstWhere('product_id', '019f7a6e-4d46-7376-aca4-000000000099')['success']);
        $this->assertSame(1, (int) $publish->json('data.succeeded'));
        $this->assertSame(2, (int) $publish->json('data.failed'));

        $this->assertSame(
            ProductLifecycleStatus::Active,
            Product::query()->whereKey($ready->id)->value('lifecycle_status'),
        );

        $archive = $this->postJson('/api/v1/admin/products/bulk-action', [
            'action_key' => 'archive',
            'product_ids' => [$ready->id],
        ]);

        $archive->assertOk()
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.results.0.success', true);

        $this->assertSame(
            ProductLifecycleStatus::Archived,
            Product::query()->whereKey($ready->id)->value('lifecycle_status'),
        );
    }

    public function test_bulk_pricing_updates_variant_prices_engine(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->fromChina()->create([
            'name' => 'Bulk Price Blouse',
            'slug' => 'bulk-price-blouse',
            'price' => 20000,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Black / S',
            'sku' => 'BULK-PRICE-BLK-S',
            'price' => null,
            'is_active' => true,
        ]);
        $price = VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 10000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/products/bulk-action', [
            'action_key' => 'pricing_percentage_increase',
            'product_ids' => [$product->id],
            'payload' => ['percent' => 10],
        ])->assertOk()->assertJsonPath('data.succeeded', 1);

        $this->assertSame('11000.00', (string) VariantPrice::query()->whereKey($price->id)->value('amount'));

        $this->postJson('/api/v1/admin/products/bulk-action', [
            'action_key' => 'pricing_fixed',
            'product_ids' => [$product->id],
            'payload' => ['amount' => 15500],
        ])->assertOk()->assertJsonPath('data.succeeded', 1);

        $this->assertSame('15500.00', (string) VariantPrice::query()->whereKey($price->id)->value('amount'));

        $this->postJson('/api/v1/admin/products/bulk-action', [
            'action_key' => 'pricing_percentage_decrease',
            'product_ids' => [$product->id],
            'payload' => ['percent' => 50],
        ])->assertOk()->assertJsonPath('data.succeeded', 1);

        $this->assertSame('7750.00', (string) VariantPrice::query()->whereKey($price->id)->value('amount'));
    }

    public function test_bulk_inventory_updates_variant_inventories(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->fromChina()->create([
            'name' => 'Bulk Stock Blouse',
            'slug' => 'bulk-stock-blouse',
            'price' => 20000,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Blue / M',
            'sku' => 'BULK-STOCK-BLU-M',
            'is_active' => true,
        ]);
        $inventory = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 10,
            'reserved' => 0,
            'reorder_level' => 2,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/products/bulk-action', [
            'action_key' => 'inventory_increase',
            'product_ids' => [$product->id],
            'payload' => ['quantity' => 5],
        ])->assertOk()->assertJsonPath('data.succeeded', 1);

        $this->assertSame(15, (int) VariantInventory::query()->whereKey($inventory->id)->value('on_hand'));

        $this->postJson('/api/v1/admin/products/bulk-action', [
            'action_key' => 'inventory_decrease',
            'product_ids' => [$product->id],
            'payload' => ['quantity' => 3],
        ])->assertOk()->assertJsonPath('data.succeeded', 1);

        $this->assertSame(12, (int) VariantInventory::query()->whereKey($inventory->id)->value('on_hand'));

        $this->postJson('/api/v1/admin/products/bulk-action', [
            'action_key' => 'inventory_set',
            'product_ids' => [$product->id],
            'payload' => ['quantity' => 7],
        ])->assertOk()->assertJsonPath('data.succeeded', 1);

        $this->assertSame(7, (int) VariantInventory::query()->whereKey($inventory->id)->value('on_hand'));
    }

    public function test_bulk_pricing_requires_pricing_manage_permission(): void
    {
        Sanctum::actingAs(
            Admin::factory()->ordinary()->withPermissions([
                AdminPermissions::CATALOG_VIEW,
                AdminPermissions::CATALOG_UPDATE,
            ])->create(),
        );

        $product = Product::factory()->fromChina()->create([
            'slug' => 'bulk-price-forbidden',
            'price' => 1000,
        ]);

        $this->postJson('/api/v1/admin/products/bulk-action', [
            'action_key' => 'pricing_fixed',
            'product_ids' => [$product->id],
            'payload' => ['amount' => 2000],
        ])->assertForbidden();
    }

    public function test_bulk_inventory_requires_inventory_adjust_permission(): void
    {
        Sanctum::actingAs(
            Admin::factory()->ordinary()->withPermissions([
                AdminPermissions::CATALOG_VIEW,
                AdminPermissions::CATALOG_UPDATE,
            ])->create(),
        );

        $product = Product::factory()->fromChina()->create([
            'slug' => 'bulk-stock-forbidden',
            'price' => 1000,
        ]);

        $this->postJson('/api/v1/admin/products/bulk-action', [
            'action_key' => 'inventory_set',
            'product_ids' => [$product->id],
            'payload' => ['quantity' => 3],
        ])->assertForbidden();
    }

    public function test_bulk_simple_product_pricing_updates_product_price(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->fromChina()->create([
            'name' => 'Simple Bulk Mug',
            'slug' => 'simple-bulk-mug',
            'price' => 8000,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/admin/products/bulk-action', [
            'action_key' => 'pricing_percentage_increase',
            'product_ids' => [$product->id],
            'payload' => ['percent' => 25],
        ])->assertOk()->assertJsonPath('data.succeeded', 1);

        $this->assertSame('10000.00', (string) Product::query()->whereKey($product->id)->value('price'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePublishableTzProduct(array $overrides = []): Product
    {
        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        $channelId = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->value('id')
            ?? CommerceChannel::factory()->tanzania()->create()->id;

        $store = Store::query()->create([
            'code' => 'TZ'.strtoupper(substr((string) str()->uuid(), 0, 4)),
            'name' => 'Bulk TZ Store',
            'slug' => 'bulk-tz-store-'.str()->random(6),
            'is_active' => true,
        ]);

        $product = Product::factory()->create(array_merge([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_demo' => false,
            'visibility' => ProductVisibility::Public,
            'price' => 10000,
            'category_id' => $subcategory->id,
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => $channelId,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'store_id' => $store->id,
        ], $overrides));

        Inventory::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => 10,
                'reserved_quantity' => 0,
                'low_stock_threshold' => 2,
            ],
        );

        return $product->fresh(['inventory', 'variants']) ?? $product;
    }
}
