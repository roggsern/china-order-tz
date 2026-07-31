<?php

namespace Tests\Feature\Storefront;

use App\Enums\CatalogOrigin;
use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductShippingOption;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubcategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrosswalkProductDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private CommerceChannel $china;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CommerceChannelSeeder::class);
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);

        $this->china = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->firstOrFail();
    }

    public function test_electronics_phones_page_returns_iphone_product(): void
    {
        $iphoneCategory = Category::query()
            ->where('slug', 'phones-tablets-smartphones-iphones')
            ->firstOrFail();

        $brand = Brand::factory()->create(['is_active' => true]);
        $iphone = $this->makeChinaListableProduct(
            $iphoneCategory,
            $brand,
            'iphone-15-pro-max-discovery',
        );

        $storefrontSlugs = $this->getJson('/api/v1/storefront/china/products?category=electronics-phones')
            ->assertOk()
            ->json('data.*.slug');

        $this->assertContains($iphone->slug, $storefrontSlugs);

        $catalogSlugs = $this->getJson('/api/v1/products?category=electronics-phones')
            ->assertOk()
            ->json('data.*.slug');

        $this->assertContains($iphone->slug, $catalogSlugs);
    }

    public function test_electronics_accessories_returns_mapped_products(): void
    {
        $accessoriesCategory = Category::query()
            ->where('slug', 'electronics-accessories')
            ->firstOrFail();

        $brand = Brand::factory()->create(['is_active' => true]);
        $product = $this->makeChinaListableProduct(
            $accessoriesCategory,
            $brand,
            'wireless-earbuds-discovery',
        );

        $slugs = $this->getJson('/api/v1/storefront/china/products?category=electronics-accessories')
            ->assertOk()
            ->json('data.*.slug');

        $this->assertContains($product->slug, $slugs);
    }

    public function test_empty_mapped_categories_return_empty_state(): void
    {
        $this->getJson('/api/v1/storefront/china/products?category=building-materials')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/products?category=building-materials')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_orphan_categories_excluded_from_crosswalk_navigation(): void
    {
        $orphan = Category::factory()->create([
            'name' => 'Consequatur Et',
            'slug' => 'consequatur-et',
            'origin' => CatalogOrigin::China,
            'parent_id' => null,
            'store_id' => null,
            'is_active' => true,
        ]);

        $brand = Brand::factory()->create(['is_active' => true]);
        $this->makeChinaListableProduct($orphan, $brand, 'orphan-discovery-product');

        $slugs = collect(
            $this->getJson('/api/v1/categories?origin=china&china_navigation=1')
                ->assertOk()
                ->json('data'),
        )->flatMap(fn (array $root) => collect([$root['slug']])
            ->merge(collect($root['children'] ?? [])->pluck('slug')))
            ->all();

        $this->assertNotContains('consequatur-et', $slugs);
    }

    public function test_no_duplicate_products_when_crosswalk_maps_multiple_subtrees(): void
    {
        $iphoneCategory = Category::query()
            ->where('slug', 'phones-tablets-smartphones-iphones')
            ->firstOrFail();

        $brand = Brand::factory()->create(['is_active' => true]);
        $iphone = $this->makeChinaListableProduct(
            $iphoneCategory,
            $brand,
            'iphone-no-dup-test',
        );

        $response = $this->getJson('/api/v1/storefront/china/products?category=electronics')
            ->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertSame(1, $slugs->filter(fn (string $slug) => $slug === $iphone->slug)->count());
        $this->assertSame($slugs->count(), $slugs->unique()->count());
    }

    private function makeChinaListableProduct(
        Category $category,
        Brand $brand,
        string $slug,
    ): Product {
        $product = Product::factory()->create([
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
            'price' => 120000,
        ]);

        Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );

        ProductShippingOption::factory()->air(5000)->create(['product_id' => $product->id]);

        return $product;
    }
}
