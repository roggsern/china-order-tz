<?php

namespace Tests\Feature\Storefront;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Services\Storefront\ChinaStorefrontMenuCache;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChinaStorefrontMenuPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private CommerceChannel $china;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CommerceChannelSeeder::class);
        $this->china = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->firstOrFail();
        Cache::flush();
    }

    public function test_menu_returns_navigation_hierarchy_and_slim_featured_payload(): void
    {
        $this->seed(CategorySeeder::class);

        $phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();
        $brand = Brand::factory()->create([
            'name' => 'Mega Menu Brand',
            'slug' => 'mega-menu-brand',
            'is_active' => true,
            'logo' => 'https://cdn.example/logo-should-not-appear.png',
            'banner' => 'https://cdn.example/banner-should-not-appear.png',
            'country' => 'CN',
        ]);
        $product = $this->makeListableChinaProduct($phones, $brand, 'mega-menu-phone', [
            'is_featured' => true,
            'short_description' => str_repeat('Heavy description that must not ship in mega menu. ', 40),
        ]);

        $menu = $this->getJson('/api/v1/storefront/china/menu?category=electronics')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.label', 'ORDER FROM CHINA')
            ->assertJsonPath('data.active_category', 'electronics')
            ->json('data');

        $this->assertNotEmpty($menu['categories']);
        $electronics = collect($menu['categories'])->firstWhere('slug', 'electronics');
        $this->assertNotNull($electronics);
        $this->assertNotEmpty($electronics['children'] ?? []);
        $this->assertContains(
            'electronics-phones',
            collect($electronics['children'])->pluck('slug')->all(),
        );

        $brandPayload = collect($menu['brands'])->firstWhere('slug', 'mega-menu-brand');
        $this->assertNotNull($brandPayload);
        $this->assertSame(['id', 'name', 'slug'], array_keys($brandPayload));

        $featured = collect($menu['featured_products'])->firstWhere('slug', $product->slug);
        $this->assertNotNull($featured);
        $this->assertArrayNotHasKey('variants', $featured);
        $this->assertArrayNotHasKey('inventory', $featured);
        $this->assertArrayNotHasKey('stock', $featured);
        $this->assertArrayNotHasKey('shipping_prices', $featured);
        $this->assertArrayNotHasKey('short_description', $featured);
        $this->assertArrayNotHasKey('average_rating', $featured);
        $this->assertArrayHasKey('id', $featured);
        $this->assertArrayHasKey('slug', $featured);
        $this->assertArrayHasKey('name', $featured);
        $this->assertArrayHasKey('primary_image', $featured);
        $this->assertSame('Mega Menu Brand', $featured['brand']['name'] ?? null);
    }

    public function test_menu_cache_is_deterministic_and_avoids_repeat_db_work(): void
    {
        $this->seed(CategorySeeder::class);

        $phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();
        $brand = Brand::factory()->create(['is_active' => true]);
        $this->makeListableChinaProduct($phones, $brand, 'cached-menu-phone', [
            'is_featured' => true,
        ]);

        $cache = app(ChinaStorefrontMenuCache::class);
        $this->assertSame('storefront:china:menu:v3:electronics', $cache->key('electronics'));
        $this->assertSame('storefront:china:menu:v3:__root__', $cache->key(null));
        $this->assertSame('storefront:china:menu:v3:__root__', $cache->key(''));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/storefront/china/menu?category=electronics')->assertOk();
        $coldQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->getJson('/api/v1/storefront/china/menu?category=electronics')->assertOk();
        $warmQueries = count(DB::getQueryLog());

        $this->assertGreaterThan(0, $coldQueries);
        $this->assertLessThan($coldQueries, $warmQueries);
        $this->assertLessThanOrEqual(8, $warmQueries);
        $this->assertTrue(Cache::has($cache->key('electronics')));
    }

    public function test_menu_root_request_defaults_active_category_to_first_navigation_root(): void
    {
        $this->seed(CategorySeeder::class);

        $phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();
        $brand = Brand::factory()->create(['is_active' => true]);
        $this->makeListableChinaProduct($phones, $brand, 'root-menu-phone', [
            'is_featured' => true,
        ]);

        $menu = $this->getJson('/api/v1/storefront/china/menu')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($menu['categories']);
        $this->assertSame($menu['categories'][0]['slug'], $menu['active_category']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeListableChinaProduct(
        Category $category,
        Brand $brand,
        string $slug,
        array $overrides = [],
    ): Product {
        $product = Product::factory()->create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'store_id' => null,
            'commerce_channel_id' => $this->china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 45000,
        ], $overrides));

        Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );
        ProductShippingOption::factory()->air(5000)->create(['product_id' => $product->id]);

        return $product;
    }
}
