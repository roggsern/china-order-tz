<?php

namespace Tests\Feature\Catalog;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ChinaCommercialStock;
use App\Models\CommerceChannel;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\Store;
use App\Models\VariantInventory;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Database\Seeders\TzStoreCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProductCardStockTest extends TestCase
{
    use RefreshDatabase;

    private CommerceChannel $china;

    private CommerceChannel $tz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CommerceChannelSeeder::class);
        $this->china = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->firstOrFail();
        $this->tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
    }

    public function test_china_listing_exposes_simple_product_stock(): void
    {
        $this->seed(CategorySeeder::class);
        $phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();
        $brand = Brand::factory()->create(['is_active' => true]);

        $product = Product::factory()->create([
            'slug' => 'china-simple-in-stock',
            'name' => 'China Simple In Stock',
            'category_id' => $phones->id,
            'brand_id' => $brand->id,
            'store_id' => null,
            'commerce_channel_id' => $this->china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 55000,
        ]);

        ProductShippingOption::factory()->air(9000)->create([
            'product_id' => $product->id,
            'is_available' => true,
        ]);

        ChinaCommercialStock::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'available_quantity' => 10,
            'reserved_quantity' => 0,
            'ordered_quantity' => 0,
        ]);

        $response = $this->getJson('/api/v1/storefront/china/products?category=electronics')
            ->assertOk();

        $card = collect($response->json('data'))->firstWhere('slug', $product->slug);
        $this->assertNotNull($card);
        $this->assertSame(10, $card['stock']);
        $this->assertTrue($card['in_stock']);
        $this->assertSame(10, $card['inventory']['available_quantity']);
        $this->assertArrayNotHasKey(0, $card['variants'] ?? []);
    }

    public function test_tz_listing_exposes_simple_product_stock(): void
    {
        $this->seed(StoreSeeder::class);
        $this->seed(TzStoreCategorySeeder::class);

        $store = Store::query()->where('slug', 'zion-mode')->firstOrFail();
        $category = Category::query()->where('store_id', $store->id)->where('name', 'Dresses')->firstOrFail();

        $product = Product::factory()->create([
            'slug' => 'tz-simple-in-stock',
            'name' => 'TZ Simple In Stock',
            'store_id' => $store->id,
            'category_id' => $category->id,
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
            ['quantity' => 7, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );

        $response = $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products')
            ->assertOk();

        $card = collect($response->json('data'))->firstWhere('slug', $product->slug);
        $this->assertNotNull($card);
        $this->assertSame(7, $card['stock']);
        $this->assertTrue($card['in_stock']);
        $this->assertSame(CommerceChannelCode::TzLocal->value, $card['commerce_channel_code']);
    }

    public function test_general_listing_exposes_out_of_stock_simple_product(): void
    {
        $product = Product::factory()->create([
            'slug' => 'general-oos-simple',
            'price' => 18000,
            'is_active' => true,
        ]);

        Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 0, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $product->slug)
            ->assertJsonPath('data.0.stock', 0)
            ->assertJsonPath('data.0.in_stock', false);
    }

    public function test_listing_variant_product_omits_parent_stock_and_exposes_variant_stock(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::chinaPurchasable(28000, 6);
        $product->forceFill([
            'slug' => 'variant-listing-phone',
            'visibility' => ProductVisibility::Public,
            'store_id' => null,
        ])->save();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $product->slug)
            ->assertJsonMissingPath('data.0.stock')
            ->assertJsonMissingPath('data.0.in_stock')
            ->assertJsonPath('data.0.variants.0.id', $variant->id)
            ->assertJsonPath('data.0.variants.0.stock', 6)
            ->assertJsonPath('data.0.variants.0.in_stock', true)
            ->assertJsonPath('data.0.variants.0.inventory.available_quantity', 6);
    }

    public function test_china_listing_exposes_commercial_variant_stock_on_china_storefront(): void
    {
        $this->seed(CategorySeeder::class);
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::chinaPurchasable(28000, 9);
        $phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();
        $brand = Brand::factory()->create(['is_active' => true]);

        $product->forceFill([
            'slug' => 'china-commercial-variant',
            'category_id' => $phones->id,
            'brand_id' => $brand->id,
            'store_id' => null,
            'visibility' => ProductVisibility::Public,
        ])->save();

        $response = $this->getJson('/api/v1/storefront/china/products?category=electronics')
            ->assertOk();

        $card = collect($response->json('data'))->firstWhere('slug', $product->slug);
        $this->assertNotNull($card);
        $this->assertSame($variant->id, $card['variants'][0]['id'] ?? null);
        $this->assertSame(9, $card['variants'][0]['stock'] ?? null);
        $this->assertTrue($card['variants'][0]['in_stock'] ?? false);
        $this->assertSame(9, $card['variants'][0]['inventory']['available_quantity'] ?? null);
    }

    public function test_listing_variant_product_with_zero_stock(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(28000, 0);
        $product->update(['slug' => 'variant-oos-phone']);

        VariantInventory::query()
            ->where('product_variant_id', $variant->id)
            ->update(['on_hand' => 0, 'reserved' => 0]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $product->slug)
            ->assertJsonMissingPath('data.0.stock')
            ->assertJsonPath('data.0.variants.0.stock', 0)
            ->assertJsonPath('data.0.variants.0.in_stock', false);
    }
}
