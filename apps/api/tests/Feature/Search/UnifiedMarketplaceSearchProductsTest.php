<?php

namespace Tests\Feature\Search;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\Store;
use App\Services\Stores\StoreService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedMarketplaceSearchProductsTest extends TestCase
{
    use RefreshDatabase;

    private CommerceChannel $china;

    private CommerceChannel $tz;

    private Category $phones;

    private Brand $zionBrand;

    private Store $zionStore;

    private Category $zionCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CommerceChannelSeeder::class);
        $this->seed(CategorySeeder::class);

        $this->china = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->firstOrFail();
        $this->tz = CommerceChannel::query()
            ->where('code', CommerceChannelCode::TzLocal->value)
            ->firstOrFail();
        $this->phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();

        $this->zionBrand = Brand::factory()->create([
            'name' => 'Zion Mode',
            'slug' => 'zion-mode-brand',
            'is_active' => true,
        ]);

        $this->zionStore = app(StoreService::class)->create([
            'code' => 'ZION',
            'name' => 'ZION MODE',
            'slug' => 'zion-mode',
            'is_active' => true,
            'storefront_enabled' => true,
            'storefront_visible' => true,
        ]);

        $this->zionCategory = Category::factory()->create([
            'store_id' => $this->zionStore->id,
            'slug' => 'zion-dresses',
            'name' => 'Zion Dresses',
            'is_active' => true,
        ]);
    }

    public function test_zion_all_returns_china_and_tz_results(): void
    {
        $chinaProduct = $this->makeChinaListableProduct('china-zion-products-hit', [
            'name' => 'Imported Evening Gown',
            'brand_id' => $this->zionBrand->id,
        ]);
        $tzProduct = $this->makeTzListableProduct('tz-zion-products-hit', [
            'name' => 'Local Boutique Dress',
        ]);

        $response = $this->getJson('/api/v1/search/products?q=zion&scope=all')
            ->assertOk()
            ->assertJsonPath('success', true);

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertContains($chinaProduct->slug, $slugs);
        $this->assertContains($tzProduct->slug, $slugs);

        $chinaHit = collect($response->json('data'))->firstWhere('slug', $chinaProduct->slug);
        $tzHit = collect($response->json('data'))->firstWhere('slug', $tzProduct->slug);
        $this->assertSame('china', $chinaHit['marketplace'] ?? null);
        $this->assertSame('tz', $tzHit['marketplace'] ?? null);
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertSame('all', $response->json('meta.scope'));
        $this->assertSame('zion', $response->json('meta.q'));
    }

    public function test_china_scope_excludes_tz_products(): void
    {
        $chinaProduct = $this->makeChinaListableProduct('china-scope-products', [
            'name' => 'Zion China Scope Product',
            'brand_id' => $this->zionBrand->id,
        ]);
        $tzProduct = $this->makeTzListableProduct('tz-excluded-products', [
            'name' => 'Zion TZ Excluded Product',
        ]);

        $slugs = collect(
            $this->getJson('/api/v1/search/products?q=zion&scope=china')
                ->assertOk()
                ->json('data'),
        )->pluck('slug')->all();

        $this->assertContains($chinaProduct->slug, $slugs);
        $this->assertNotContains($tzProduct->slug, $slugs);
        $this->assertTrue(
            collect($this->getJson('/api/v1/search/products?q=zion&scope=china')->json('data'))
                ->every(fn (array $row) => ($row['marketplace'] ?? null) === 'china'),
        );
    }

    public function test_tz_scope_excludes_china_products(): void
    {
        $chinaProduct = $this->makeChinaListableProduct('china-excluded-products', [
            'name' => 'Zion China Excluded Product',
            'brand_id' => $this->zionBrand->id,
        ]);
        $tzProduct = $this->makeTzListableProduct('tz-scope-products', [
            'name' => 'Zion TZ Scope Product',
        ]);

        $slugs = collect(
            $this->getJson('/api/v1/search/products?q=zion&scope=tz')
                ->assertOk()
                ->json('data'),
        )->pluck('slug')->all();

        $this->assertContains($tzProduct->slug, $slugs);
        $this->assertNotContains($chinaProduct->slug, $slugs);
    }

    public function test_hidden_draft_and_inactive_products_excluded(): void
    {
        $visible = $this->makeChinaListableProduct('china-zion-visible-products', [
            'name' => 'Zion Visible Import',
            'brand_id' => $this->zionBrand->id,
        ]);
        $hidden = $this->makeChinaListableProduct('china-zion-hidden-products', [
            'name' => 'Zion Hidden Import',
            'brand_id' => $this->zionBrand->id,
            'visibility' => ProductVisibility::Hidden,
        ]);
        $draft = $this->makeChinaListableProduct('china-zion-draft-products', [
            'name' => 'Zion Draft Import',
            'brand_id' => $this->zionBrand->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);
        $inactive = $this->makeChinaListableProduct('china-zion-inactive-products', [
            'name' => 'Zion Inactive Import',
            'brand_id' => $this->zionBrand->id,
            'is_active' => false,
        ]);
        $tzVisible = $this->makeTzListableProduct('tz-zion-visible-products', [
            'name' => 'Zion Visible Local',
        ]);
        $tzDraft = $this->makeTzListableProduct('tz-zion-draft-products', [
            'name' => 'Zion Draft Local',
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $slugs = collect(
            $this->getJson('/api/v1/search/products?q=zion&scope=all')->assertOk()->json('data'),
        )->pluck('slug')->all();

        $this->assertContains($visible->slug, $slugs);
        $this->assertContains($tzVisible->slug, $slugs);
        $this->assertNotContains($hidden->slug, $slugs);
        $this->assertNotContains($draft->slug, $slugs);
        $this->assertNotContains($inactive->slug, $slugs);
        $this->assertNotContains($tzDraft->slug, $slugs);
    }

    public function test_empty_q_returns_empty_results_with_meta(): void
    {
        $this->makeChinaListableProduct('china-should-not-list', [
            'name' => 'Zion Should Not List',
            'brand_id' => $this->zionBrand->id,
        ]);

        $this->getJson('/api/v1/search/products?q=')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('meta.q', '');

        $this->getJson('/api/v1/search/products?q=%20%20')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_pagination_metadata(): void
    {
        $this->makeChinaListableProduct('china-zion-page-a', [
            'name' => 'Zion Page Alpha',
            'brand_id' => $this->zionBrand->id,
        ]);
        $this->makeChinaListableProduct('china-zion-page-b', [
            'name' => 'Zion Page Beta',
            'brand_id' => $this->zionBrand->id,
        ]);
        $this->makeChinaListableProduct('china-zion-page-c', [
            'name' => 'Zion Page Gamma',
            'brand_id' => $this->zionBrand->id,
        ]);

        $page1 = $this->getJson('/api/v1/search/products?q=zion&scope=china&per_page=2&page=1')
            ->assertOk();

        $this->assertCount(2, $page1->json('data'));
        $this->assertSame(3, $page1->json('meta.total'));
        $this->assertSame(2, $page1->json('meta.per_page'));
        $this->assertSame(1, $page1->json('meta.current_page'));
        $this->assertSame(2, $page1->json('meta.last_page'));

        $page2 = $this->getJson('/api/v1/search/products?q=zion&scope=china&per_page=2&page=2')
            ->assertOk();

        $this->assertCount(1, $page2->json('data'));
        $this->assertSame(2, $page2->json('meta.current_page'));
        $this->assertSame(2, $page2->json('meta.last_page'));

        $allSlugs = collect($page1->json('data'))
            ->merge($page2->json('data'))
            ->pluck('slug')
            ->unique()
            ->count();
        $this->assertSame(3, $allSlugs);
    }

    public function test_relevance_ranks_name_match_above_description_match(): void
    {
        $descriptionOnly = $this->makeChinaListableProduct('china-desc-only-products', [
            'name' => 'Plain Accessory Kit',
            'description' => 'Includes ZenithProbe ceramic insert for testing',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        $nameMatch = $this->makeChinaListableProduct('china-name-rank-products', [
            'name' => 'ZenithProbe Travel Case',
            'description' => 'Generic travel packaging',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $slugs = collect(
            $this->getJson('/api/v1/search/products?q=ZenithProbe&scope=china&sort=relevance')
                ->assertOk()
                ->json('data'),
        )->pluck('slug')->values()->all();

        $this->assertContains($nameMatch->slug, $slugs);
        $this->assertContains($descriptionOnly->slug, $slugs);
        $this->assertLessThan(
            array_search($descriptionOnly->slug, $slugs, true),
            array_search($nameMatch->slug, $slugs, true),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeChinaListableProduct(string $slug, array $overrides = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'category_id' => $this->phones->id,
            'brand_id' => $this->zionBrand->id,
            'store_id' => null,
            'commerce_channel_id' => $this->china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 45000,
            'sku' => 'SKU-'.strtoupper($slug),
        ], $overrides));

        Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );
        ProductShippingOption::factory()->air(5000)->create(['product_id' => $product->id]);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeTzListableProduct(string $slug, array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'store_id' => $this->zionStore->id,
            'category_id' => $this->zionCategory->id,
            'brand_id' => null,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 25000,
            'sku' => 'TZ-'.strtoupper($slug),
        ], $overrides));
    }
}
