<?php

namespace Tests\Feature\Catalog;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\VariantPriceType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Inventory\CatalogStockPresenter;
use App\Services\Stores\StoreService;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Database\Seeders\TzStoreCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: TZ_LOCAL listing must resolve store warehouse inventory the same way as PDP.
 * Stock at store location (not MAIN) previously failed when nested variant.product omitted store_id.
 */
class CustomerProductCardTzListingParityTest extends TestCase
{
    use RefreshDatabase;

    private CommerceChannel $tz;

    private Store $zion;

    private Store $rovi;

    private Category $category;

    private StoreService $stores;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CommerceChannelSeeder::class);
        $this->seed(StoreSeeder::class);
        $this->seed(TzStoreCategorySeeder::class);

        $this->tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
        $this->zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();
        $this->rovi = Store::query()->where('slug', 'rovi-beauty')->firstOrFail();
        $this->category = Category::query()->where('store_id', $this->zion->id)->where('name', 'Dresses')->firstOrFail();
        $this->stores = app(StoreService::class);
    }

    public function test_listing_eager_load_includes_store_id_on_nested_variant_product(): void
    {
        $product = $this->makeTzConfigurableProduct(
            slug: 'eager-store-id-check',
            retail: 10000,
            onHand: 2,
            warehouse: 'store',
        );

        $product->load(CatalogStockPresenter::catalogListingEagerLoads());
        $nested = $product->variants->first()?->product;

        $this->assertNotNull($nested);
        $this->assertSame($this->zion->id, $nested->store_id);
    }

    public function test_tz_store_warehouse_variant_listing_matches_pdp_price_and_purchasability(): void
    {
        $product = $this->makeTzConfigurableProduct(
            slug: 'stretch-pencil-dresses-parity',
            retail: 40000,
            onHand: 4,
            warehouse: 'store',
        );

        $listing = $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products')
            ->assertOk();
        $card = collect($listing->json('data'))->firstWhere('slug', $product->slug);
        $this->assertNotNull($card);

        $pdp = $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->json('data');

        $this->assertTrue($card['is_purchasable']);
        $this->assertTrue($pdp['is_purchasable']);
        $this->assertSame('available', $card['availability_status']);
        $this->assertSame('available', $pdp['availability_status']);
        $this->assertSame('40000.00', $card['price']);
        $this->assertSame('40000.00', $pdp['price']);
        $this->assertSame($card['is_purchasable'], $pdp['is_purchasable']);
        $this->assertSame($card['availability_status'], $pdp['availability_status']);
        $this->assertSame($card['price'], $pdp['price']);
    }

    public function test_tz_variant_with_only_main_inventory_is_not_required_for_this_regression(): void
    {
        // Guard: store-warehouse fixture must not invent MAIN rows.
        $product = $this->makeTzConfigurableProduct(
            slug: 'zion-store-wh-only',
            retail: 25000,
            onHand: 3,
            warehouse: 'store',
        );

        $variantId = $product->variants()->value('id');
        $this->assertNotNull($variantId);
        $this->assertSame(
            0,
            VariantInventory::query()
                ->where('product_variant_id', $variantId)
                ->where('warehouse_code', 'MAIN')
                ->count(),
        );
        $this->assertGreaterThan(
            0,
            VariantInventory::query()
                ->where('product_variant_id', $variantId)
                ->where('warehouse_code', '!=', 'MAIN')
                ->count(),
        );
    }

    public function test_tz_variant_without_store_inventory_is_unavailable_on_listing(): void
    {
        $product = $this->makeTzConfigurableProduct(
            slug: 'zion-no-store-stock',
            retail: 40000,
            onHand: 0,
            warehouse: 'none',
        );

        $card = collect(
            $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products')->assertOk()->json('data'),
        )->firstWhere('slug', $product->slug);

        $this->assertNotNull($card);
        $this->assertFalse($card['is_purchasable']);
        $this->assertSame('unavailable', $card['availability_status']);
        $this->assertSame('0.00', $card['price']);
    }

    public function test_other_store_inventory_does_not_make_zion_product_purchasable(): void
    {
        $product = $this->makeTzConfigurableProduct(
            slug: 'zion-wrong-store-stock',
            retail: 40000,
            onHand: 8,
            warehouse: 'other_store',
        );

        // TZ store corpus excludes variants that only have another store's warehouse stock.
        $card = collect(
            $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products')->assertOk()->json('data'),
        )->firstWhere('slug', $product->slug);
        $this->assertNull($card);

        $pdp = $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->json('data');

        $this->assertFalse($pdp['is_purchasable']);
        $this->assertSame('unavailable', $pdp['availability_status']);
    }

    public function test_tz_simple_product_listing_unchanged(): void
    {
        $product = Product::factory()->create([
            'slug' => 'tz-simple-parity',
            'name' => 'TZ Simple Parity',
            'store_id' => $this->zion->id,
            'category_id' => $this->category->id,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 32000,
        ]);

        Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );

        $card = collect(
            $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products')->assertOk()->json('data'),
        )->firstWhere('slug', $product->slug);

        $this->assertNotNull($card);
        $this->assertTrue($card['is_purchasable']);
        $this->assertSame('32000.00', $card['price']);
        $this->assertSame(5, $card['stock']);
    }

    /**
     * @param  'store'|'none'|'other_store'  $warehouse
     */
    private function makeTzConfigurableProduct(
        string $slug,
        float $retail,
        int $onHand,
        string $warehouse,
    ): Product {
        $product = Product::factory()->create([
            'slug' => $slug,
            'name' => 'Stretch Pencil Dresses',
            'store_id' => $this->zion->id,
            'category_id' => $this->category->id,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 0,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'is_default' => true,
            'price' => null,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => $retail,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        if ($warehouse === 'store') {
            $location = $this->stores->defaultLocation($this->zion);
            VariantInventory::query()->create([
                'product_variant_id' => $variant->id,
                'warehouse_code' => strtoupper((string) $location->code),
                'inventory_location_id' => $location->id,
                'on_hand' => $onHand,
                'reserved' => 0,
                'reorder_level' => 1,
                'safety_stock' => 0,
                'is_active' => true,
            ]);
        } elseif ($warehouse === 'other_store') {
            $location = $this->stores->defaultLocation($this->rovi);
            VariantInventory::query()->create([
                'product_variant_id' => $variant->id,
                'warehouse_code' => strtoupper((string) $location->code),
                'inventory_location_id' => $location->id,
                'on_hand' => $onHand,
                'reserved' => 0,
                'reorder_level' => 1,
                'safety_stock' => 0,
                'is_active' => true,
            ]);
        }

        return $product->fresh(['variants.prices', 'variants.inventories', 'store', 'commerceChannel']) ?? $product;
    }
}
