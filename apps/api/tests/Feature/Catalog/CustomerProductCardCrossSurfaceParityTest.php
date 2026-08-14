<?php

namespace Tests\Feature\Catalog;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsFeaturedItemType;
use App\Enums\CMS\CmsFeaturedSourceType;
use App\Enums\CMS\CmsHomepageSectionType;
use App\Enums\CMS\CmsStatus;
use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\VariantPriceType;
use App\Http\Resources\CustomerProductCardResource;
use App\Models\Category;
use App\Models\CmsFeaturedContent;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use App\Models\CommerceChannel;
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
 * Cross-surface product-card parity: homepage CMS featured, catalog featured,
 * TZ store listing, search, and PDP must agree for TZ_LOCAL store-warehouse stock.
 */
class CustomerProductCardCrossSurfaceParityTest extends TestCase
{
    use RefreshDatabase;

    private CommerceChannel $tz;

    private Store $zion;

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
        $this->category = Category::query()->where('store_id', $this->zion->id)->where('name', 'Dresses')->firstOrFail();
        $this->stores = app(StoreService::class);
    }

    public function test_listing_eager_loads_helper_includes_store_id(): void
    {
        $product = $this->makeTzStoreWarehouseProduct('card-eager-store-id', 40000, 4);
        $product->load(CustomerProductCardResource::listingEagerLoads());

        $nested = $product->variants->first()?->product;
        $this->assertNotNull($nested);
        $this->assertSame($this->zion->id, $nested->store_id);
        $this->assertArrayHasKey('variants', CatalogStockPresenter::catalogListingEagerLoads());
    }

    public function test_homepage_cms_featured_matches_tz_listing_and_pdp(): void
    {
        $product = $this->makeTzStoreWarehouseProduct('cms-featured-stretch-parity', 40000, 4);
        $this->seedFeaturedHomepage($product);

        $cmsItem = $this->cmsFeaturedProductPayload($product->id);
        $this->assertNotNull($cmsItem);

        $listing = collect(
            $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products')->assertOk()->json('data'),
        )->firstWhere('slug', $product->slug);
        $this->assertNotNull($listing);

        $pdp = $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->json('data');
        $featured = collect(
            $this->getJson('/api/v1/products?featured=1&per_page=48')->assertOk()->json('data'),
        )->firstWhere('slug', $product->slug);
        $this->assertNotNull($featured);

        foreach ([$cmsItem, $listing, $featured, $pdp] as $payload) {
            $this->assertTrue($payload['is_purchasable'], 'expected purchasable on '.json_encode($payload['slug'] ?? null));
            $this->assertSame('available', $payload['availability_status']);
            $this->assertSame('40000.00', $payload['price']);
        }
    }

    public function test_search_results_match_tz_listing_for_store_warehouse_product(): void
    {
        $product = $this->makeTzStoreWarehouseProduct('search-stretch-parity', 40000, 3);

        $searchHit = collect(
            $this->getJson('/api/v1/search/products?q='.urlencode($product->name).'&per_page=24')
                ->assertOk()
                ->json('data'),
        )->firstWhere('slug', $product->slug);

        $this->assertNotNull($searchHit);
        $this->assertTrue($searchHit['is_purchasable']);
        $this->assertSame('available', $searchHit['availability_status']);
        $this->assertSame('40000.00', $searchHit['price']);
    }

    public function test_cms_featured_other_store_inventory_does_not_sell(): void
    {
        $product = $this->makeTzStoreWarehouseProduct(
            slug: 'cms-wrong-store-stock',
            retail: 40000,
            onHand: 8,
            warehouse: 'other_store',
        );
        $this->seedFeaturedHomepage($product);

        $cmsItem = $this->cmsFeaturedProductPayload($product->id);
        $this->assertNotNull($cmsItem);
        $this->assertFalse($cmsItem['is_purchasable']);
        $this->assertSame('unavailable', $cmsItem['availability_status']);
    }

    public function test_china_listing_still_exposes_commercial_variant_stock(): void
    {
        $this->seed(\Database\Seeders\CategorySeeder::class);
        ['product' => $product, 'variant' => $variant] = \Database\Factories\Support\CatalogCartFixture::chinaPurchasable(28000, 9);
        $phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();
        $product->forceFill([
            'slug' => 'china-cross-surface-variant',
            'category_id' => $phones->id,
            'store_id' => null,
            'visibility' => ProductVisibility::Public,
            'is_featured' => true,
        ])->save();

        $card = collect(
            $this->getJson('/api/v1/storefront/china/products?category=electronics')->assertOk()->json('data'),
        )->firstWhere('slug', $product->slug);

        $this->assertNotNull($card);
        $this->assertSame($variant->id, $card['variants'][0]['id'] ?? null);
        $this->assertSame(9, $card['variants'][0]['stock'] ?? null);
        $this->assertTrue($card['is_purchasable']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cmsFeaturedProductPayload(string $productId): ?array
    {
        $response = $this->getJson(
            '/api/v1/storefront/homepage?commerce_context='.CmsCommerceContext::Global->value,
        )->assertOk();

        $sections = collect($response->json('data.sections') ?? []);
        $featured = $sections->firstWhere('section_type', CmsHomepageSectionType::FeaturedProducts->value)
            ?? $sections->first();

        $items = collect($featured['featured_contents'][0]['items'] ?? []);
        $item = $items->firstWhere('id', $productId);

        return is_array($item) ? ($item['data'] ?? null) : null;
    }

    private function seedFeaturedHomepage(Product $product): void
    {
        $layout = CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::Global)
            ->create([
                'status' => CmsStatus::Active,
                'is_default' => true,
            ]);

        $section = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'section_type' => CmsHomepageSectionType::FeaturedProducts,
            'is_visible' => true,
            'position' => 1,
        ]);

        CmsFeaturedContent::factory()->active()->create([
            'cms_homepage_section_id' => $section->id,
            'title' => 'Featured',
            'source_type' => CmsFeaturedSourceType::Manual,
            'configuration' => [
                'item_type' => CmsFeaturedItemType::Product->value,
                'item_ids' => [$product->id],
            ],
            'is_visible' => true,
            'limit' => 8,
        ]);
    }

    /**
     * @param  'store'|'other_store'  $warehouse
     */
    private function makeTzStoreWarehouseProduct(
        string $slug,
        float $retail,
        int $onHand,
        string $warehouse = 'store',
    ): Product {
        $product = Product::factory()->create([
            'slug' => $slug,
            'name' => 'Stretch Pencil Dresses Parity',
            'store_id' => $this->zion->id,
            'category_id' => $this->category->id,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'is_featured' => true,
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

        $store = $warehouse === 'store' ? $this->zion : Store::query()->where('slug', 'rovi-beauty')->firstOrFail();
        $location = $this->stores->defaultLocation($store);

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

        $this->assertSame(
            0,
            VariantInventory::query()
                ->where('product_variant_id', $variant->id)
                ->where('warehouse_code', 'MAIN')
                ->count(),
        );

        return $product->fresh(['variants', 'store', 'commerceChannel']) ?? $product;
    }
}
