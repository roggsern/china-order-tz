<?php

namespace Tests\Feature\Storefront;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Services\Stores\StoreService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChinaStorefrontSearchTest extends TestCase
{
    use RefreshDatabase;

    private CommerceChannel $china;

    private CommerceChannel $tz;

    private Category $phones;

    private Brand $brand;

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
        $this->brand = Brand::factory()->create([
            'name' => 'SearchBrandCo',
            'slug' => 'search-brand-co',
            'is_active' => true,
        ]);
    }

    public function test_china_search_returns_matching_china_products_only(): void
    {
        $chinaMatch = $this->makeChinaListableProduct('china-galaxy-search', [
            'name' => 'Galaxy Ultra Search Phone',
            'short_description' => 'Flagship China import handset',
            'sku' => 'CHINA-GALAXY-1',
        ]);
        $chinaOther = $this->makeChinaListableProduct('china-unrelated-search', [
            'name' => 'Kitchen Blender Set',
            'sku' => 'CHINA-BLEND-1',
        ]);

        $slugs = collect(
            $this->getJson('/api/v1/storefront/china/products?search=galaxy')
                ->assertOk()
                ->assertJsonPath('success', true)
                ->json('data'),
        )->pluck('slug')->all();

        $this->assertContains($chinaMatch->slug, $slugs);
        $this->assertNotContains($chinaOther->slug, $slugs);
    }

    public function test_china_search_excludes_tz_local_products(): void
    {
        $chinaMatch = $this->makeChinaListableProduct('china-shared-name', [
            'name' => 'Shared Search Widget',
        ]);

        $store = app(StoreService::class)->create([
            'code' => 'TZSRCH',
            'name' => 'TZ Search Store',
            'slug' => 'tz-search-store',
            'is_active' => true,
            'storefront_enabled' => true,
            'storefront_visible' => true,
        ]);
        $tzCategory = Category::factory()->create([
            'store_id' => $store->id,
            'slug' => 'tz-search-cat',
            'is_active' => true,
        ]);
        $tzMatch = Product::factory()->create([
            'name' => 'Shared Search Widget',
            'slug' => 'tz-shared-search-widget',
            'store_id' => $store->id,
            'category_id' => $tzCategory->id,
            'brand_id' => $this->brand->id,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 22000,
        ]);

        $slugs = collect(
            $this->getJson('/api/v1/storefront/china/products?search=Shared%20Search%20Widget')
                ->assertOk()
                ->json('data'),
        )->pluck('slug')->all();

        $this->assertContains($chinaMatch->slug, $slugs);
        $this->assertNotContains($tzMatch->slug, $slugs);
        $this->assertTrue(
            collect($this->getJson('/api/v1/storefront/china/products?search=Shared%20Search%20Widget')->json('data'))
                ->every(fn (array $row) => ($row['commerce_channel_code'] ?? null) === CommerceChannelCode::ChinaImport->value
                    || ! array_key_exists('commerce_channel_code', $row)),
        );
    }

    public function test_china_search_excludes_hidden_draft_and_inactive_products(): void
    {
        $visible = $this->makeChinaListableProduct('china-visible-search', [
            'name' => 'Aurora Search Lamp',
        ]);
        $hidden = $this->makeChinaListableProduct('china-hidden-search', [
            'name' => 'Aurora Search Lamp Hidden',
            'visibility' => ProductVisibility::Hidden,
        ]);
        $draft = $this->makeChinaListableProduct('china-draft-search', [
            'name' => 'Aurora Search Lamp Draft',
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);
        $inactive = $this->makeChinaListableProduct('china-inactive-search', [
            'name' => 'Aurora Search Lamp Inactive',
            'is_active' => false,
        ]);

        $slugs = collect(
            $this->getJson('/api/v1/storefront/china/products?search=Aurora%20Search%20Lamp')
                ->assertOk()
                ->json('data'),
        )->pluck('slug')->all();

        $this->assertContains($visible->slug, $slugs);
        $this->assertNotContains($hidden->slug, $slugs);
        $this->assertNotContains($draft->slug, $slugs);
        $this->assertNotContains($inactive->slug, $slugs);
    }

    public function test_china_search_matches_brand_sku_and_product_type(): void
    {
        $type = CatalogProductType::factory()->create([
            'name' => 'Searchable Handset Type',
            'slug' => 'searchable-handset-type',
            'is_active' => true,
        ]);

        $byBrand = $this->makeChinaListableProduct('china-brand-hit', [
            'name' => 'Unrelated Title Alpha',
            'sku' => 'SKU-ALPHA-1',
        ]);
        $bySku = $this->makeChinaListableProduct('china-sku-hit', [
            'name' => 'Unrelated Title Beta',
            'sku' => 'UNIQUE-SEARCH-SKU-99',
        ]);
        $byType = $this->makeChinaListableProduct('china-type-hit', [
            'name' => 'Unrelated Title Gamma',
            'sku' => 'SKU-GAMMA-1',
            'catalog_product_type_id' => $type->id,
        ]);

        $this->assertContains(
            $byBrand->slug,
            collect($this->getJson('/api/v1/storefront/china/products?search=SearchBrandCo')->assertOk()->json('data'))
                ->pluck('slug')
                ->all(),
        );
        $this->assertContains(
            $bySku->slug,
            collect($this->getJson('/api/v1/storefront/china/products?search=UNIQUE-SEARCH-SKU-99')->assertOk()->json('data'))
                ->pluck('slug')
                ->all(),
        );
        $this->assertContains(
            $byType->slug,
            collect($this->getJson('/api/v1/storefront/china/products?search=Searchable%20Handset%20Type')->assertOk()->json('data'))
                ->pluck('slug')
                ->all(),
        );
    }

    public function test_china_search_excludes_products_failing_sellability_rules(): void
    {
        $sellable = $this->makeChinaListableProduct('china-sellable-search', [
            'name' => 'Orbit Search Speaker',
        ]);

        $noShipping = Product::factory()->create([
            'name' => 'Orbit Search Speaker No Ship',
            'slug' => 'china-no-shipping-search',
            'category_id' => $this->phones->id,
            'brand_id' => $this->brand->id,
            'store_id' => null,
            'commerce_channel_id' => $this->china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 45000,
            'sku' => 'SKU-NO-SHIP',
        ]);
        Inventory::query()->updateOrCreate(
            ['product_id' => $noShipping->id, 'product_variant_id' => null],
            ['quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );

        $noInventory = Product::factory()->create([
            'name' => 'Orbit Search Speaker No Stock',
            'slug' => 'china-no-inventory-search',
            'category_id' => $this->phones->id,
            'brand_id' => $this->brand->id,
            'store_id' => null,
            'commerce_channel_id' => $this->china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 45000,
            'sku' => 'SKU-NO-STOCK',
        ]);
        ProductShippingOption::factory()->air(5000)->create(['product_id' => $noInventory->id]);

        $slugs = collect(
            $this->getJson('/api/v1/storefront/china/products?search=Orbit%20Search%20Speaker')
                ->assertOk()
                ->json('data'),
        )->pluck('slug')->all();

        $this->assertContains($sellable->slug, $slugs);
        $this->assertNotContains($noShipping->slug, $slugs);
        $this->assertNotContains($noInventory->slug, $slugs);
    }

    public function test_china_search_matches_description(): void
    {
        $match = $this->makeChinaListableProduct('china-description-hit', [
            'name' => 'Plain Title Device',
            'description' => 'Contains zirconium ceramic detail for search',
        ]);

        $slugs = collect(
            $this->getJson('/api/v1/storefront/china/products?search=zirconium')
                ->assertOk()
                ->json('data'),
        )->pluck('slug')->all();

        $this->assertContains($match->slug, $slugs);
    }

    public function test_empty_search_behaves_like_normal_china_listing(): void
    {
        $listed = $this->makeChinaListableProduct('china-empty-search-listed', [
            'name' => 'Listed China Camera',
        ]);

        $baseline = collect(
            $this->getJson('/api/v1/storefront/china/products')->assertOk()->json('data'),
        )->pluck('slug')->sort()->values()->all();

        $empty = collect(
            $this->getJson('/api/v1/storefront/china/products?search=')->assertOk()->json('data'),
        )->pluck('slug')->sort()->values()->all();

        $whitespace = collect(
            $this->getJson('/api/v1/storefront/china/products?search=%20%20')->assertOk()->json('data'),
        )->pluck('slug')->sort()->values()->all();

        $this->assertContains($listed->slug, $baseline);
        $this->assertSame($baseline, $empty);
        $this->assertSame($baseline, $whitespace);
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
            'brand_id' => $this->brand->id,
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
}
