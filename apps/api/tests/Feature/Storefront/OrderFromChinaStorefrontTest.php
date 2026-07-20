<?php

namespace Tests\Feature\Storefront;

use App\Enums\CatalogOrigin;
use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Models\Store;
use App\Services\Stores\StoreService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\RoleSeeder;
use Database\Support\CatalogBible;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFromChinaStorefrontTest extends TestCase
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

    public function test_menu_uses_catalog_bible_and_excludes_sample_and_store_categories(): void
    {
        $this->seed(CategorySeeder::class);

        // Faker / sample orphan root with China origin — must not appear in menu.
        Category::factory()->create([
            'name' => 'Et Rerum',
            'slug' => 'et-rerum-sample',
            'origin' => CatalogOrigin::China,
            'parent_id' => null,
            'store_id' => null,
            'is_active' => true,
        ]);

        $store = app(StoreService::class)->create([
            'code' => 'ZION',
            'name' => 'ZION MODE',
            'slug' => 'zion-mode',
            'is_active' => true,
            'storefront_enabled' => true,
            'storefront_visible' => true,
        ]);

        Category::query()->create([
            'name' => 'Bras',
            'slug' => 'zion-bras-sample',
            'origin' => CatalogOrigin::Tz,
            'store_id' => $store->id,
            'parent_id' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/v1/storefront/china/categories')
            ->assertOk()
            ->assertJsonPath('success', true);

        $names = collect($response->json('data'))->pluck('name')->all();
        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $bibleRoots = collect(CatalogBible::categories())->pluck('slug')->all();
        foreach ($slugs as $slug) {
            $this->assertContains($slug, $bibleRoots);
        }

        $this->assertNotContains('Et Rerum', $names);
        $this->assertNotContains('Bras', $names);
        $this->assertNotContains('Incidunt Atque', $names);
        $this->assertNotContains('Clothing', $names);
        $this->assertNotContains('Smartphones', $names);
        $this->assertNotContains('Wigs', $names);
    }

    public function test_china_products_included_tz_and_inactive_excluded(): void
    {
        $this->seed(CategorySeeder::class);
        $electronics = Category::query()->where('slug', 'electronics')->firstOrFail();
        $phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();
        $brand = Brand::factory()->create(['name' => 'Apple', 'slug' => 'apple-china-test', 'is_active' => true]);

        $chinaProduct = $this->makeProduct($phones, $brand, 'china-phone', $this->china, [
            'is_featured' => true,
            'name' => 'China Phone',
        ]);
        $tzProduct = $this->makeProduct($phones, $brand, 'tz-phone', $this->tz, [
            'name' => 'TZ Phone',
        ]);
        $inactive = $this->makeProduct($phones, $brand, 'china-inactive', $this->china, [
            'is_active' => false,
            'name' => 'Inactive China',
        ]);
        $draft = $this->makeProduct($phones, $brand, 'china-draft', $this->china, [
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'name' => 'Draft China',
        ]);
        $storeProduct = $this->makeProduct($phones, $brand, 'store-phone', $this->china, [
            'store_id' => app(StoreService::class)->create([
                'code' => 'ROVI',
                'name' => 'ROVI',
                'slug' => 'rovi-beauty',
            ])->id,
            'name' => 'Store Scoped China Channel',
        ]);

        $products = $this->getJson('/api/v1/storefront/china/products?category=electronics')
            ->assertOk()
            ->json('data');

        $slugs = collect($products)->pluck('slug')->all();
        $this->assertContains($chinaProduct->slug, $slugs);
        $this->assertNotContains($tzProduct->slug, $slugs);
        $this->assertNotContains($inactive->slug, $slugs);
        $this->assertNotContains($draft->slug, $slugs);
        $this->assertNotContains($storeProduct->slug, $slugs);

        $menu = $this->getJson('/api/v1/storefront/china/menu?category=electronics')
            ->assertOk()
            ->assertJsonPath('data.label', 'ORDER FROM CHINA')
            ->json('data');

        $this->assertNotEmpty($menu['categories']);
        $featuredSlugs = collect($menu['featured_products'])->pluck('slug')->all();
        $this->assertContains($chinaProduct->slug, $featuredSlugs);
        $this->assertNotContains($tzProduct->slug, $featuredSlugs);

        $brands = collect($menu['brands'])->pluck('slug')->all();
        $this->assertContains('apple-china-test', $brands);
    }

    public function test_origin_china_list_excludes_store_scoped_categories(): void
    {
        $this->seed(CategorySeeder::class);
        $store = app(StoreService::class)->create([
            'code' => 'PEACHY',
            'name' => 'PEACHY',
            'slug' => 'peachy-lingerie',
        ]);
        Category::query()->create([
            'name' => 'Wigs',
            'slug' => 'peachy-wigs-leak',
            'origin' => CatalogOrigin::China, // mis-tagged store category
            'store_id' => $store->id,
            'parent_id' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $slugs = collect($this->getJson('/api/v1/categories?origin=china')->assertOk()->json('data'))
            ->pluck('slug')
            ->all();

        $this->assertNotContains('peachy-wigs-leak', $slugs);
        $this->assertContains('electronics', $slugs);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProduct(
        Category $category,
        Brand $brand,
        string $slug,
        CommerceChannel $channel,
        array $overrides = [],
    ): Product {
        return Product::factory()->create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'store_id' => null,
            'commerce_channel_id' => $channel->id,
            'fulfillment_source' => $channel->code === CommerceChannelCode::TzLocal->value
                ? CommerceChannelCode::TzLocal->fulfillmentSource()
                : CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 45000,
        ], $overrides));
    }
}
