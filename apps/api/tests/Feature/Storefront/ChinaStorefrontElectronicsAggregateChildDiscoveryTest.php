<?php

namespace Tests\Feature\Storefront;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Services\Storefront\ChinaStorefrontDiscoveryCache;
use App\Services\Storefront\ChinaStorefrontMenuCache;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubcategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChinaStorefrontElectronicsAggregateChildDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private CommerceChannel $china;

    private Brand $brand;

    private Category $phones;

    private Category $audioLeaf;

    private Category $networkingLeaf;

    private Department $professionalAudio;

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
        $this->brand = Brand::factory()->create(['is_active' => true]);
        $this->phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();
        $this->audioLeaf = Category::query()
            ->where('slug', 'professional-audio-speakers')
            ->firstOrFail();
        $this->networkingLeaf = Category::query()
            ->where('slug', 'computers-office-networking-power-dc-ups-router-backup')
            ->firstOrFail();
        $this->professionalAudio = Department::query()
            ->where('slug', 'professional-audio')
            ->firstOrFail();
        Cache::flush();
    }

    public function test_sellable_professional_audio_product_appends_electronics_audio_child(): void
    {
        $product = $this->makeListableChinaProduct($this->audioLeaf, 'pa-stage-box', [
            'name' => 'BEHRINGER S32 Stage Box',
        ]);

        $menu = $this->getJson('/api/v1/storefront/china/menu?category=electronics')
            ->assertOk()
            ->json('data');

        $electronics = collect($menu['categories'])->firstWhere('slug', 'electronics');
        $this->assertNotNull($electronics);
        $childSlugs = collect($electronics['children'] ?? [])->pluck('slug')->all();

        $this->assertContains('electronics-audio', $childSlugs);
        $this->assertSame(1, collect($childSlugs)->filter(fn ($slug) => $slug === 'electronics-audio')->count());
        $this->assertNotContains('professional-audio', $childSlugs);
        $this->assertNotContains('professional-audio-speakers', $childSlugs);

        $audioChild = collect($electronics['children'])->firstWhere('slug', 'electronics-audio');
        $this->assertSame($this->professionalAudio->name, $audioChild['name']);

        $rootSlugs = collect($menu['categories'])->pluck('slug')->all();
        $this->assertContains('electronics', $rootSlugs);
        $this->assertNotContains('professional-audio', $rootSlugs);

        $featured = collect($menu['featured_products'] ?? [])->pluck('slug')->all();
        $this->assertContains($product->slug, $featured);

        $plp = collect($this->getJson('/api/v1/storefront/china/products?category=electronics-audio')
            ->assertOk()
            ->json('data'))
            ->pluck('slug')
            ->all();
        $this->assertContains($product->slug, $plp);

        $electronicsPlp = collect($this->getJson('/api/v1/storefront/china/products?category=electronics')
            ->assertOk()
            ->json('data'))
            ->pluck('slug')
            ->all();
        $this->assertContains($product->slug, $electronicsPlp);
    }

    public function test_curated_networking_power_stays_before_appended_audio_and_is_not_flattened(): void
    {
        $this->makeListableChinaProduct($this->networkingLeaf, 'dc-ups-nav');
        $this->makeListableChinaProduct($this->audioLeaf, 'pa-mixer');

        $menu = $this->getJson('/api/v1/storefront/china/menu?category=electronics')
            ->assertOk()
            ->json('data');

        $electronics = collect($menu['categories'])->firstWhere('slug', 'electronics');
        $this->assertNotNull($electronics);
        $childSlugs = collect($electronics['children'] ?? [])->pluck('slug')->all();

        $this->assertContains('electronics-networking-power', $childSlugs);
        $this->assertContains('electronics-audio', $childSlugs);
        $this->assertNotContains('computers-office-networking-power-dc-ups-router-backup', $childSlugs);
        $this->assertNotContains('computers-office-networking-power', $childSlugs);
        $this->assertNotContains('professional-audio-speakers', $childSlugs);

        $networkingIndex = array_search('electronics-networking-power', $childSlugs, true);
        $audioIndex = array_search('electronics-audio', $childSlugs, true);
        $this->assertNotFalse($networkingIndex);
        $this->assertNotFalse($audioIndex);
        $this->assertLessThan($audioIndex, $networkingIndex);
    }

    public function test_empty_electronics_consumer_aggregate_does_not_appear(): void
    {
        $this->makeListableChinaProduct($this->audioLeaf, 'pa-only');

        $childSlugs = collect(
            $this->getJson('/api/v1/storefront/china/menu?category=electronics')->json('data.categories')
        )->firstWhere('slug', 'electronics')['children'] ?? [];

        $slugs = collect($childSlugs)->pluck('slug')->all();
        $this->assertContains('electronics-audio', $slugs);
        $this->assertNotContains('electronics-consumer', $slugs);
        $this->assertNotContains('consumer-electronics', $slugs);
    }

    public function test_non_sellable_professional_audio_product_does_not_append_child(): void
    {
        $this->makeListableChinaProduct($this->phones, 'elec-phone');
        $this->makeListableChinaProduct($this->audioLeaf, 'pa-draft', [
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $childSlugs = collect(
            $this->getJson('/api/v1/storefront/china/menu?category=electronics')->json('data.categories')
        )->firstWhere('slug', 'electronics')['children'] ?? [];

        $this->assertNotContains(
            'electronics-audio',
            collect($childSlugs)->pluck('slug')->all(),
        );
    }

    public function test_first_sellable_product_invalidates_stale_electronics_children(): void
    {
        $this->makeListableChinaProduct($this->phones, 'cache-phone');
        $this->getJson('/api/v1/storefront/china/menu?category=electronics')->assertOk();

        $menuCache = app(ChinaStorefrontMenuCache::class);
        $discovery = app(ChinaStorefrontDiscoveryCache::class);
        $staleKey = $menuCache->key('electronics');
        $generationBefore = $discovery->generation();
        $this->assertTrue(Cache::has($staleKey));

        $this->makeListableChinaProduct($this->audioLeaf, 'cache-pa-box');

        $this->assertGreaterThan($generationBefore, $discovery->generation());
        $this->assertNotSame($staleKey, $menuCache->key('electronics'));

        $childSlugs = collect(
            $this->getJson('/api/v1/storefront/china/menu?category=electronics')->json('data.categories')
        )->firstWhere('slug', 'electronics')['children'] ?? [];

        $this->assertContains('electronics-audio', collect($childSlugs)->pluck('slug')->all());
    }

    public function test_unpublishing_last_sellable_audio_product_removes_appended_child(): void
    {
        $product = $this->makeListableChinaProduct($this->audioLeaf, 'last-pa-box');

        $this->assertContains(
            'electronics-audio',
            collect(
                collect($this->getJson('/api/v1/storefront/china/menu?category=electronics')->json('data.categories'))
                    ->firstWhere('slug', 'electronics')['children'] ?? []
            )->pluck('slug')->all(),
        );

        $product->forceFill(['lifecycle_status' => ProductLifecycleStatus::Draft])->save();

        $this->assertNotContains(
            'electronics-audio',
            collect(
                collect($this->getJson('/api/v1/storefront/china/menu?category=electronics')->json('data.categories'))
                    ->firstWhere('slug', 'electronics')['children'] ?? []
            )->pluck('slug')->all(),
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
