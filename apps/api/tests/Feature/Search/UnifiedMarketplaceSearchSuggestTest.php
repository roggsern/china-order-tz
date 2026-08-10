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

class UnifiedMarketplaceSearchSuggestTest extends TestCase
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

    public function test_zion_returns_china_brand_match_product(): void
    {
        $chinaProduct = $this->makeChinaListableProduct('china-zion-brand-hit', [
            'name' => 'Imported Evening Gown',
            'brand_id' => $this->zionBrand->id,
        ]);

        $products = collect(
            $this->getJson('/api/v1/search/suggest?q=zion&scope=all')
                ->assertOk()
                ->assertJsonPath('success', true)
                ->json('data.products'),
        );

        $hit = $products->firstWhere('slug', $chinaProduct->slug);
        $this->assertNotNull($hit);
        $this->assertSame('china', $hit['marketplace']);
        $this->assertSame(CommerceChannelCode::ChinaImport->value, $hit['commerce_channel_code']);
        $this->assertSame('Zion Mode', $hit['brand']['name'] ?? null);
        $this->assertContains('brand', $hit['matched_on'] ?? []);
        $this->assertGreaterThan(0, $hit['relevance_score'] ?? 0);
    }

    public function test_zion_returns_tz_store_match_product(): void
    {
        $tzProduct = $this->makeTzListableProduct('tz-zion-store-hit', [
            'name' => 'Local Boutique Dress',
        ]);

        $products = collect(
            $this->getJson('/api/v1/search/suggest?q=zion&scope=all')
                ->assertOk()
                ->json('data.products'),
        );

        $hit = $products->firstWhere('slug', $tzProduct->slug);
        $this->assertNotNull($hit);
        $this->assertSame('tz', $hit['marketplace']);
        $this->assertSame(CommerceChannelCode::TzLocal->value, $hit['commerce_channel_code']);
        $this->assertSame('zion-mode', $hit['store']['slug'] ?? null);
        $this->assertContains('store', $hit['matched_on'] ?? []);
        $this->assertGreaterThan(0, $hit['relevance_score'] ?? 0);
    }

    public function test_brand_and_store_are_separate_buckets(): void
    {
        $this->makeChinaListableProduct('china-zion-for-brand-bucket', [
            'name' => 'China Zion Accessory',
            'brand_id' => $this->zionBrand->id,
        ]);
        $this->makeTzListableProduct('tz-zion-for-store-bucket', [
            'name' => 'TZ Zion Accessory',
        ]);

        $data = $this->getJson('/api/v1/search/suggest?q=zion&scope=all')
            ->assertOk()
            ->json('data');

        $brandSlugs = collect($data['brands'])->pluck('slug')->all();
        $storeSlugs = collect($data['stores'])->pluck('slug')->all();
        $brandKinds = collect($data['brands'])->pluck('kind')->unique()->all();
        $storeKinds = collect($data['stores'])->pluck('kind')->unique()->all();

        $this->assertContains('zion-mode-brand', $brandSlugs);
        $this->assertContains('zion-mode', $storeSlugs);
        $this->assertSame(['catalog_brand'], $brandKinds);
        $this->assertSame(['tz_store'], $storeKinds);
        $this->assertEmpty(array_intersect($brandSlugs, $storeSlugs));
    }

    public function test_scope_china_excludes_tz_products_and_stores(): void
    {
        $chinaProduct = $this->makeChinaListableProduct('china-scope-only', [
            'name' => 'Zion China Scope',
            'brand_id' => $this->zionBrand->id,
        ]);
        $tzProduct = $this->makeTzListableProduct('tz-scope-excluded', [
            'name' => 'Zion TZ Scope',
        ]);

        $data = $this->getJson('/api/v1/search/suggest?q=zion&scope=china')
            ->assertOk()
            ->json('data');

        $slugs = collect($data['products'])->pluck('slug')->all();
        $this->assertContains($chinaProduct->slug, $slugs);
        $this->assertNotContains($tzProduct->slug, $slugs);
        $this->assertSame([], $data['stores']);
        $this->assertTrue(
            collect($data['products'])->every(fn (array $row) => ($row['marketplace'] ?? null) === 'china'),
        );
    }

    public function test_scope_tz_excludes_china_products_and_brands(): void
    {
        $chinaProduct = $this->makeChinaListableProduct('china-excluded-from-tz', [
            'name' => 'Zion China Excluded',
            'brand_id' => $this->zionBrand->id,
        ]);
        $tzProduct = $this->makeTzListableProduct('tz-scope-only', [
            'name' => 'Zion TZ Only',
        ]);

        $data = $this->getJson('/api/v1/search/suggest?q=zion&scope=tz')
            ->assertOk()
            ->json('data');

        $slugs = collect($data['products'])->pluck('slug')->all();
        $this->assertContains($tzProduct->slug, $slugs);
        $this->assertNotContains($chinaProduct->slug, $slugs);
        $this->assertSame([], $data['brands']);
        $this->assertTrue(
            collect($data['products'])->every(fn (array $row) => ($row['marketplace'] ?? null) === 'tz'),
        );
    }

    public function test_hidden_draft_and_inactive_products_excluded(): void
    {
        $visible = $this->makeChinaListableProduct('china-zion-visible', [
            'name' => 'Zion Visible Import',
            'brand_id' => $this->zionBrand->id,
        ]);
        $hidden = $this->makeChinaListableProduct('china-zion-hidden', [
            'name' => 'Zion Hidden Import',
            'brand_id' => $this->zionBrand->id,
            'visibility' => ProductVisibility::Hidden,
        ]);
        $draft = $this->makeChinaListableProduct('china-zion-draft', [
            'name' => 'Zion Draft Import',
            'brand_id' => $this->zionBrand->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);
        $inactive = $this->makeChinaListableProduct('china-zion-inactive', [
            'name' => 'Zion Inactive Import',
            'brand_id' => $this->zionBrand->id,
            'is_active' => false,
        ]);

        $tzVisible = $this->makeTzListableProduct('tz-zion-visible', [
            'name' => 'Zion Visible Local',
        ]);
        $tzDraft = $this->makeTzListableProduct('tz-zion-draft', [
            'name' => 'Zion Draft Local',
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $slugs = collect(
            $this->getJson('/api/v1/search/suggest?q=zion&scope=all')->assertOk()->json('data.products'),
        )->pluck('slug')->all();

        $this->assertContains($visible->slug, $slugs);
        $this->assertContains($tzVisible->slug, $slugs);
        $this->assertNotContains($hidden->slug, $slugs);
        $this->assertNotContains($draft->slug, $slugs);
        $this->assertNotContains($inactive->slug, $slugs);
        $this->assertNotContains($tzDraft->slug, $slugs);
    }

    public function test_empty_query_returns_empty_buckets(): void
    {
        $this->makeChinaListableProduct('china-should-not-appear', [
            'name' => 'Zion Should Not Appear',
            'brand_id' => $this->zionBrand->id,
        ]);

        $this->getJson('/api/v1/search/suggest?q=')
            ->assertOk()
            ->assertJsonPath('data.q', '')
            ->assertJsonPath('data.products', [])
            ->assertJsonPath('data.brands', [])
            ->assertJsonPath('data.stores', [])
            ->assertJsonPath('data.categories', []);

        $this->getJson('/api/v1/search/suggest?q=%20%20')
            ->assertOk()
            ->assertJsonPath('data.products', [])
            ->assertJsonPath('data.brands', [])
            ->assertJsonPath('data.stores', [])
            ->assertJsonPath('data.categories', []);
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
