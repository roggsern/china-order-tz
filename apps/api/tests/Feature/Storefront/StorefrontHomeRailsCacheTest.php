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
use App\Services\Storefront\StorefrontPublicResponseCache;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontHomeRailsCacheTest extends TestCase
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

    public function test_featured_collections_cache_avoids_repeat_db_work(): void
    {
        $this->seed(CategorySeeder::class);
        $phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();
        $brand = Brand::factory()->create(['is_active' => true]);
        $this->makeListableChinaProduct($phones, $brand, 'rail-featured-phone');

        $cache = app(StorefrontPublicResponseCache::class);
        $this->assertStringStartsWith('storefront:public:v1:china-featured-collections:', $cache->key('china-featured-collections', 'default'));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $first = $this->getJson('/api/v1/storefront/china/featured-collections')->assertOk()->json('data');
        $cold = count(DB::getQueryLog());

        DB::flushQueryLog();
        $second = $this->getJson('/api/v1/storefront/china/featured-collections')->assertOk()->json('data');
        $warm = count(DB::getQueryLog());

        $this->assertSame($first, $second);
        $this->assertGreaterThan(0, $cold);
        $this->assertLessThan($cold, $warm);
        $this->assertLessThanOrEqual(8, $warm);
    }

    public function test_products_list_cache_skips_search_queries(): void
    {
        $this->seed(CategorySeeder::class);
        $phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();
        $brand = Brand::factory()->create(['is_active' => true]);
        $this->makeListableChinaProduct($phones, $brand, 'rail-list-phone', [
            'is_featured' => true,
            'name' => 'Searchable Rail Phone',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/products?featured=1&per_page=8')->assertOk();
        $cold = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->getJson('/api/v1/products?featured=1&per_page=8')->assertOk();
        $warm = count(DB::getQueryLog());

        $this->assertGreaterThan(0, $cold);
        $this->assertLessThan($cold, $warm);

        // Search must not use the shared public list cache path as a personalized query.
        DB::flushQueryLog();
        $this->getJson('/api/v1/products?search=Searchable%20Rail%20Phone&per_page=8')->assertOk();
        $searchCold = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->getJson('/api/v1/products?search=Searchable%20Rail%20Phone&per_page=8')->assertOk();
        $searchAgain = count(DB::getQueryLog());
        $this->assertGreaterThan(0, $searchCold);
        $this->assertGreaterThan(5, $searchAgain);
    }

    public function test_china_products_and_tz_stores_use_deterministic_public_cache_keys(): void
    {
        $cache = app(StorefrontPublicResponseCache::class);
        $request = \Illuminate\Http\Request::create('/api/v1/storefront/china/products', 'GET', [
            'per_page' => 12,
            'page' => 1,
        ]);

        $a = $cache->chinaProductListVariant($request);
        $b = $cache->chinaProductListVariant($request);
        $this->assertSame($a, $b);
        $this->assertSame(
            $cache->key('tz-stores', 'visible'),
            $cache->key('tz-stores', 'visible'),
        );
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
