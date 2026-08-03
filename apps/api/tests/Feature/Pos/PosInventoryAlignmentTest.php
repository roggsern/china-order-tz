<?php

namespace Tests\Feature\Pos;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Inventory\AdminInventoryApplicationService;
use App\Services\Inventory\StockResolver;
use App\Services\Stores\StoreAssignmentService;
use App\Services\Stores\StoreService;
use Database\Seeders\CoreDatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosInventoryAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private StoreService $stores;

    private StoreAssignmentService $assignments;

    private CommerceChannel $tz;

    private CommerceChannel $china;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CoreDatabaseSeeder::class);
        $this->stores = app(StoreService::class);
        $this->assignments = app(StoreAssignmentService::class);

        $this->tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
        $this->china = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->firstOrFail();
    }

    public function test_tz_simple_product_stock_writes_to_store_location(): void
    {
        $store = Store::query()->where('slug', 'rovi-beauty')->firstOrFail();
        $location = $store->defaultInventoryLocation;

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'price' => 25000,
        ]);

        app(AdminInventoryApplicationService::class)->setSimpleProductStock($product, 50);

        $simple = Inventory::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->first();

        $this->assertNotNull($simple);
        $this->assertSame(50, (int) $simple->quantity);

        $defaultVariant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($defaultVariant);

        $storeInventory = VariantInventory::query()
            ->where('product_variant_id', $defaultVariant->id)
            ->where('inventory_location_id', $location->id)
            ->where('warehouse_code', $store->code)
            ->first();

        $this->assertNotNull($storeInventory);
        $this->assertSame(50, (int) $storeInventory->on_hand);

        $resolver = app(StockResolver::class);
        $resolved = $resolver->resolveSimpleProduct($product->fresh(['commerceChannel', 'store']));
        $this->assertSame(50, $resolved->quantityAvailable);
    }

    public function test_tz_variant_inventory_defaults_to_store_location(): void
    {
        $store = Store::query()->where('slug', 'rovi-beauty')->firstOrFail();
        $location = $store->defaultInventoryLocation;

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'price' => 0,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'price' => 45000,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 45000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        app(AdminInventoryApplicationService::class)->createVariantInventory($variant, [
            'on_hand' => 20,
        ]);

        $storeInventory = VariantInventory::query()
            ->where('product_variant_id', $variant->id)
            ->where('inventory_location_id', $location->id)
            ->where('warehouse_code', $store->code)
            ->first();

        $this->assertNotNull($storeInventory);
        $this->assertSame(20, (int) $storeInventory->on_hand);
        $this->assertFalse(
            VariantInventory::query()
                ->where('product_variant_id', $variant->id)
                ->where('warehouse_code', 'MAIN')
                ->exists()
        );

        $resolved = app(StockResolver::class)->resolveVariantProduct(
            $variant->fresh(['inventories', 'product.commerceChannel', 'product.store']),
            product: $product->fresh(['commerceChannel', 'store']),
        );
        $this->assertSame(20, $resolved->quantityAvailable);
    }

    public function test_china_variant_inventory_still_uses_main(): void
    {
        $product = Product::factory()->create([
            'store_id' => null,
            'commerce_channel_id' => $this->china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        app(AdminInventoryApplicationService::class)->createVariantInventory($variant, [
            'on_hand' => 15,
        ]);

        $main = VariantInventory::query()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_code', 'MAIN')
            ->first();

        $this->assertNotNull($main);
        $this->assertSame(15, (int) $main->on_hand);
        $this->assertNull($main->inventory_location_id);
    }

    public function test_pos_catalog_shows_simple_and_variant_stock(): void
    {
        $store = $this->stores->create(['code' => 'ALIGN', 'name' => 'Alignment Store']);
        $location = $store->defaultInventoryLocation;
        $cashier = $this->makeCashier($store);

        $category = Category::factory()->forStore($store)->create();

        $simple = Product::factory()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'name' => 'Simple Lotion',
            'sku' => 'LOT-001',
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'price' => 12000,
        ]);
        app(AdminInventoryApplicationService::class)->setSimpleProductStock($simple, 50);

        $variantProduct = Product::factory()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'name' => 'Silk Wig',
            'sku' => 'WIG-001',
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'price' => 0,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $variantProduct->id,
            'name' => '18 inch',
            'sku' => 'WIG-001-18',
            'is_active' => true,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 85000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        app(AdminInventoryApplicationService::class)->createVariantInventory($variant, ['on_hand' => 20]);

        $this->openPosSession($cashier, $store);

        $response = $this->getJson('/api/v1/admin/pos/catalog?q=');
        $response->assertOk();

        $rows = collect($response->json('data'));
        $simpleRow = $rows->first(fn (array $row) => ($row['product_id'] ?? null) === $simple->id);
        $variantRow = $rows->first(fn (array $row) => ($row['product_variant_id'] ?? null) === $variant->id);

        $this->assertNotNull($simpleRow);
        $this->assertTrue($simpleRow['is_simple']);
        $this->assertSame(50, $simpleRow['available_stock']);

        $this->assertNotNull($variantRow);
        $this->assertFalse($variantRow['is_simple']);
        $this->assertSame(20, $variantRow['available_stock']);

        $this->assertSame($location->id, VariantInventory::query()
            ->where('product_variant_id', $variant->id)
            ->value('inventory_location_id'));
    }

    public function test_store_inventory_is_isolated_between_stores(): void
    {
        $rovi = Store::query()->where('slug', 'rovi-beauty')->firstOrFail();
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'Zion Mode']);

        $roviProduct = Product::factory()->create([
            'store_id' => $rovi->id,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'price' => 10000,
        ]);
        $zionProduct = Product::factory()->create([
            'store_id' => $zion->id,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'price' => 10000,
        ]);

        app(AdminInventoryApplicationService::class)->setSimpleProductStock($roviProduct, 30);
        app(AdminInventoryApplicationService::class)->setSimpleProductStock($zionProduct, 7);

        $roviVariant = ProductVariant::query()->where('product_id', $roviProduct->id)->firstOrFail();
        $zionVariant = ProductVariant::query()->where('product_id', $zionProduct->id)->firstOrFail();

        $this->assertSame(30, (int) VariantInventory::query()
            ->where('product_variant_id', $roviVariant->id)
            ->where('warehouse_code', $rovi->code)
            ->value('on_hand'));
        $this->assertSame(7, (int) VariantInventory::query()
            ->where('product_variant_id', $zionVariant->id)
            ->where('warehouse_code', $zion->code)
            ->value('on_hand'));
    }

    private function makeCashier(Store $store): Admin
    {
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $store, $super);

        return $cashier;
    }

    private function openPosSession(Admin $cashier, Store $store): void
    {
        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 50000,
        ])->assertCreated();
    }
}
