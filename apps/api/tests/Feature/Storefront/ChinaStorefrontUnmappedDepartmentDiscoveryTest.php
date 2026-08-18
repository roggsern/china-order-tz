<?php

namespace Tests\Feature\Storefront;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsNavigationType;
use App\Enums\CatalogOrigin;
use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsNavigationItem;
use App\Models\CmsNavigationShell;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Services\CMS\CmsNavigationResolver;
use App\Services\Storefront\ChinaStorefrontDiscoveryCache;
use App\Services\Storefront\ChinaStorefrontMenuCache;
use App\Services\Stores\StoreService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CmsDefaultNavigationShellSeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RoleSeeder;
use Database\Support\CatalogBible;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChinaStorefrontUnmappedDepartmentDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private CommerceChannel $china;

    private CommerceChannel $tz;

    private Brand $brand;

    private Category $phones;

    private Category $sportswear;

    private Department $sports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CommerceChannelSeeder::class);
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);

        $this->china = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->firstOrFail();
        $this->tz = CommerceChannel::query()
            ->where('code', CommerceChannelCode::TzLocal->value)
            ->firstOrFail();
        $this->brand = Brand::factory()->create(['is_active' => true]);
        $this->phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();
        $this->sportswear = Category::query()->where('slug', 'sports-outdoors-sportswear')->firstOrFail();
        $this->sports = Department::query()->where('slug', 'sports-outdoors')->firstOrFail();
        Cache::flush();
    }

    public function test_first_sellable_product_opens_unmapped_department_in_menu_search_and_plp(): void
    {
        $this->makeListableChinaProduct($this->phones, 'mapped-phone');
        $sportsProduct = $this->makeListableChinaProduct($this->sportswear, 'sports-jersey', [
            'name' => 'Sports Jersey Discovery',
        ]);

        $menu = $this->getJson('/api/v1/storefront/china/menu')
            ->assertOk()
            ->json('data');

        $rootSlugs = collect($menu['categories'])->pluck('slug')->all();
        $this->assertContains('electronics', $rootSlugs);
        $this->assertContains('sports-outdoors', $rootSlugs);
        $this->assertSame(1, collect($rootSlugs)->filter(fn ($slug) => $slug === 'sports-outdoors')->count());
        $sportsRoot = collect($menu['categories'])->firstWhere('slug', 'sports-outdoors');
        $this->assertNotNull($sportsRoot);
        $this->assertContains(
            'sports-outdoors-sportswear',
            collect($sportsRoot['children'] ?? [])->pluck('slug')->all(),
        );

        $bibleSlugs = collect(CatalogBible::categories())->pluck('slug')->all();
        $electronicsIndex = array_search('electronics', $rootSlugs, true);
        $sportsIndex = array_search('sports-outdoors', $rootSlugs, true);
        $this->assertNotFalse($electronicsIndex);
        $this->assertNotFalse($sportsIndex);
        $this->assertLessThan($sportsIndex, $electronicsIndex);
        $this->assertContains('electronics', $bibleSlugs);
        $this->assertNotContains('sports-outdoors', $bibleSlugs);

        $search = collect($this->getJson('/api/v1/search/products?q=Jersey&scope=china')->assertOk()->json('data'))
            ->pluck('slug')
            ->all();
        $this->assertContains($sportsProduct->slug, $search);

        $departmentPlp = collect($this->getJson('/api/v1/storefront/china/products?category=sports-outdoors')
            ->assertOk()
            ->json('data'))
            ->pluck('slug')
            ->all();
        $this->assertContains($sportsProduct->slug, $departmentPlp);

        $frontierPlp = collect($this->getJson('/api/v1/storefront/china/products?category=sports-outdoors-sportswear')
            ->assertOk()
            ->json('data'))
            ->pluck('slug')
            ->all();
        $this->assertContains($sportsProduct->slug, $frontierPlp);
    }

    public function test_second_product_in_same_department_does_not_duplicate_root(): void
    {
        $this->makeListableChinaProduct($this->sportswear, 'sports-one');
        $this->makeListableChinaProduct($this->sportswear, 'sports-two');

        $rootSlugs = collect($this->getJson('/api/v1/storefront/china/menu')->json('data.categories'))
            ->pluck('slug')
            ->all();

        $this->assertSame(1, collect($rootSlugs)->filter(fn ($slug) => $slug === 'sports-outdoors')->count());
    }

    public function test_draft_product_does_not_open_dynamic_department(): void
    {
        $this->makeListableChinaProduct($this->sportswear, 'sports-draft', [
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $rootSlugs = collect($this->getJson('/api/v1/storefront/china/menu')->json('data.categories'))
            ->pluck('slug')
            ->all();
        $this->assertNotContains('sports-outdoors', $rootSlugs);
    }

    public function test_private_product_does_not_open_dynamic_department(): void
    {
        $this->makeListableChinaProduct($this->sportswear, 'sports-private', [
            'visibility' => ProductVisibility::Private,
        ]);

        $rootSlugs = collect($this->getJson('/api/v1/storefront/china/menu')->json('data.categories'))
            ->pluck('slug')
            ->all();
        $this->assertNotContains('sports-outdoors', $rootSlugs);
    }

    public function test_zero_stock_product_does_not_open_dynamic_department(): void
    {
        $product = Product::factory()->create([
            'name' => 'Empty Stock Jersey',
            'slug' => 'sports-empty-stock',
            'category_id' => $this->sportswear->id,
            'brand_id' => $this->brand->id,
            'store_id' => null,
            'commerce_channel_id' => $this->china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 45000,
        ]);
        ProductShippingOption::factory()->air(5000)->create(['product_id' => $product->id]);

        $rootSlugs = collect($this->getJson('/api/v1/storefront/china/menu')->json('data.categories'))
            ->pluck('slug')
            ->all();
        $this->assertNotContains('sports-outdoors', $rootSlugs);
    }

    public function test_missing_shipping_does_not_open_dynamic_department(): void
    {
        $product = Product::factory()->create([
            'name' => 'No Ship Jersey',
            'slug' => 'sports-no-ship',
            'category_id' => $this->sportswear->id,
            'brand_id' => $this->brand->id,
            'store_id' => null,
            'commerce_channel_id' => $this->china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 45000,
        ]);
        Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );

        $rootSlugs = collect($this->getJson('/api/v1/storefront/china/menu')->json('data.categories'))
            ->pluck('slug')
            ->all();
        $this->assertNotContains('sports-outdoors', $rootSlugs);
    }

    public function test_tz_local_store_product_does_not_open_china_department(): void
    {
        $store = app(StoreService::class)->create([
            'code' => 'ZION',
            'name' => 'ZION MODE',
            'slug' => 'zion-mode-discovery',
            'is_active' => true,
            'storefront_enabled' => true,
            'storefront_visible' => true,
        ]);
        $tzCategory = Category::factory()->create([
            'store_id' => $store->id,
            'origin' => CatalogOrigin::Tz,
            'slug' => 'zion-sports-leak',
            'name' => 'Zion Sports',
            'is_active' => true,
        ]);
        Product::factory()->create([
            'name' => 'TZ Sports Leak',
            'slug' => 'tz-sports-leak',
            'store_id' => $store->id,
            'category_id' => $tzCategory->id,
            'brand_id' => null,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 25000,
        ]);

        $this->seed(CmsDefaultNavigationShellSeeder::class);

        $rootSlugs = collect($this->getJson('/api/v1/storefront/china/menu')->json('data.categories'))
            ->pluck('slug')
            ->all();
        $this->assertNotContains('sports-outdoors', $rootSlugs);
        $this->assertNotContains('zion-sports-leak', $rootSlugs);

        $tzStores = $this->getJson('/api/v1/storefront/tz/stores')->assertOk()->json('data');
        $this->assertNotEmpty($tzStores);

        $resolved = app(CmsNavigationResolver::class)->resolve(
            CmsCommerceContext::Global,
            CmsNavigationType::Primary,
            'guest',
            hydrateMegaMenus: true,
        );
        $tz = collect($resolved['items'])->firstWhere('title', 'Buy from TZ');
        $this->assertNotNull($tz);
        $this->assertSame('JOURNEY', $tz['item_type']);
        $this->assertSame('tz_storefront_catalog', $tz['journey']['engine']);
        $this->assertSame('TZ_LOCAL', $tz['journey']['code']);
        $this->assertArrayNotHasKey('mega_menu', $tz);
        $this->assertArrayNotHasKey('categories', $tz);
    }

    public function test_featured_collections_consider_dynamic_roots_but_remain_capped(): void
    {
        $this->makeListableChinaProduct($this->phones, 'feat-phone');
        $this->makeListableChinaProduct($this->sportswear, 'feat-sports');

        $featured = collect($this->getJson('/api/v1/storefront/china/featured-collections')
            ->assertOk()
            ->json('data'))
            ->pluck('slug')
            ->all();
        $menu = collect($this->getJson('/api/v1/storefront/china/menu')->json('data.categories'))
            ->pluck('slug')
            ->all();

        $this->assertContains('electronics', $featured);
        $this->assertContains('sports-outdoors', $featured);
        $this->assertLessThanOrEqual(6, count($featured));
        $this->assertContains('sports-outdoors', $menu);
        $this->assertGreaterThanOrEqual(count($featured), count($menu));
    }

    public function test_mapped_bible_electronics_root_is_unchanged_and_not_duplicated_as_department(): void
    {
        $this->makeListableChinaProduct($this->phones, 'elec-phone');

        $menu = $this->getJson('/api/v1/storefront/china/menu')->json('data');
        $rootSlugs = collect($menu['categories'])->pluck('slug')->all();

        $this->assertContains('electronics', $rootSlugs);
        $this->assertNotContains('phones-tablets', $rootSlugs);
        $this->assertNotContains('computers-office', $rootSlugs);
        $electronicsRoot = collect($menu['categories'])->firstWhere('slug', 'electronics');
        $this->assertNotNull($electronicsRoot);
        $this->assertContains(
            'electronics-phones',
            collect($electronicsRoot['children'] ?? [])->pluck('slug')->all(),
        );
    }

    public function test_cms_china_mega_menu_shell_still_hydrates_catalog_engine(): void
    {
        $this->seed(CmsDefaultNavigationShellSeeder::class);
        $this->makeListableChinaProduct($this->sportswear, 'cms-sports-jersey');

        $resolved = app(CmsNavigationResolver::class)->resolve(
            CmsCommerceContext::Global,
            CmsNavigationType::Primary,
            'guest',
            hydrateMegaMenus: true,
        );

        $china = collect($resolved['items'])->firstWhere('title', 'Order from China');
        $this->assertNotNull($china);
        $this->assertSame('JOURNEY', $china['item_type']);
        $this->assertSame('china_storefront_catalog', $china['journey']['engine']);
        $this->assertArrayNotHasKey('mega_menu', $china);

        $shell = CmsNavigationShell::query()
            ->where('slug', 'default-china-import-primary')
            ->firstOrFail();
        CmsNavigationItem::factory()->megaMenu(CmsCommerceContext::ChinaImport->value)->create([
            'navigation_shell_id' => $shell->id,
            'title' => 'China mega',
            'position' => 99,
        ]);

        $hydrated = app(CmsNavigationResolver::class)->resolve(
            CmsCommerceContext::ChinaImport,
            CmsNavigationType::Primary,
            'guest',
            hydrateMegaMenus: true,
        );
        $mega = collect($hydrated['items'])->firstWhere('title', 'China mega');
        $this->assertNotNull($mega);
        $this->assertSame('MEGA_MENU', $mega['item_type']);
        $this->assertSame('china_storefront_catalog', $mega['mega_menu']['engine']);
        $this->assertContains(
            'sports-outdoors',
            collect($mega['mega_menu']['categories'] ?? [])->pluck('slug')->all(),
        );
    }

    public function test_product_publish_invalidates_stale_china_menu_cache(): void
    {
        $this->makeListableChinaProduct($this->phones, 'cache-phone');
        $this->getJson('/api/v1/storefront/china/menu')->assertOk();

        $menuCache = app(ChinaStorefrontMenuCache::class);
        $discovery = app(ChinaStorefrontDiscoveryCache::class);
        $staleKey = $menuCache->key(null);
        $generationBefore = $discovery->generation();
        $this->assertTrue(Cache::has($staleKey));

        $this->makeListableChinaProduct($this->sportswear, 'cache-sports-jersey');

        $this->assertGreaterThan($generationBefore, $discovery->generation());
        $this->assertNotSame($staleKey, $menuCache->key(null));

        $rootSlugs = collect($this->getJson('/api/v1/storefront/china/menu')->json('data.categories'))
            ->pluck('slug')
            ->all();
        $this->assertContains('sports-outdoors', $rootSlugs);
    }

    public function test_unpublishing_last_sellable_product_closes_dynamic_department(): void
    {
        $product = $this->makeListableChinaProduct($this->sportswear, 'last-sports-jersey');

        $this->assertContains(
            'sports-outdoors',
            collect($this->getJson('/api/v1/storefront/china/menu')->json('data.categories'))->pluck('slug')->all(),
        );

        $product->forceFill(['lifecycle_status' => ProductLifecycleStatus::Draft])->save();

        $this->assertNotContains(
            'sports-outdoors',
            collect($this->getJson('/api/v1/storefront/china/menu')->json('data.categories'))->pluck('slug')->all(),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeListableChinaProduct(Category $category, string $slug, array $overrides = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'category_id' => $category->id,
            'brand_id' => $this->brand->id,
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
