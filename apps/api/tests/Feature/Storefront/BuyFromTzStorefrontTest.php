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
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Database\Seeders\TzStoreCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyFromTzStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private StoreService $stores;

    private CommerceChannel $tz;

    private CommerceChannel $china;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CommerceChannelSeeder::class);
        $this->stores = app(StoreService::class);
        $this->tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
        $this->china = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->firstOrFail();
    }

    public function test_only_four_valid_active_tz_stores_returned_and_brands_excluded(): void
    {
        $this->seed(StoreSeeder::class);

        Brand::factory()->create(['name' => 'Apple', 'slug' => 'apple-test-brand', 'is_active' => true]);
        Brand::factory()->create(['name' => 'Nike', 'slug' => 'nike-test-brand', 'is_active' => true]);

        $hidden = $this->stores->create([
            'code' => 'HIDDEN',
            'name' => 'Hidden POS Only',
            'slug' => 'hidden-pos',
            'is_active' => true,
            'storefront_enabled' => false,
            'storefront_visible' => false,
        ]);

        $inactive = $this->stores->create([
            'code' => 'DEAD',
            'name' => 'Inactive Store',
            'slug' => 'inactive-store',
            'is_active' => false,
            'storefront_enabled' => true,
            'storefront_visible' => true,
        ]);

        $response = $this->getJson('/api/v1/storefront/tz/stores')
            ->assertOk()
            ->assertJsonPath('success', true);

        $names = collect($response->json('data'))->pluck('name')->all();
        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertCount(4, $names);
        $this->assertEqualsCanonicalizing(
            ['ZION MODE', 'PEACHY LINGERIE', 'TZUR JEWELRY', 'ROVI BEAUTY'],
            $names,
        );
        $this->assertEqualsCanonicalizing(
            ['zion-mode', 'peachy-lingerie', 'tzur-jewelry', 'rovi-beauty'],
            $slugs,
        );
        $this->assertNotContains('Apple', $names);
        $this->assertNotContains('Nike', $names);
        $this->assertNotContains($hidden->name, $names);
        $this->assertNotContains($inactive->name, $names);

        // Legacy /stores endpoint also storefront-scoped.
        $legacy = $this->getJson('/api/v1/stores')->assertOk()->json('data');
        $this->assertCount(4, $legacy);
    }

    public function test_store_categories_and_products_are_scoped_excluding_china(): void
    {
        $this->seed(StoreSeeder::class);
        $this->seed(TzStoreCategorySeeder::class);

        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();
        $rovi = Store::query()->where('slug', 'rovi-beauty')->firstOrFail();

        $zionCategory = Category::query()->where('store_id', $zion->id)->where('name', 'Dresses')->firstOrFail();
        $roviCategory = Category::query()->where('store_id', $rovi->id)->where('name', 'Wigs')->firstOrFail();

        $zionProduct = $this->makeProduct($zion, $zionCategory, 'zion-dress', $this->tz);
        $roviProduct = $this->makeProduct($rovi, $roviCategory, 'rovi-wig', $this->tz);
        $chinaProduct = $this->makeProduct(null, null, 'china-phone', $this->china, [
            'name' => 'China Import Phone',
        ]);
        $draft = $this->makeProduct($zion, $zionCategory, 'zion-draft', $this->tz, [
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'visibility' => ProductVisibility::Public,
        ]);

        $categories = $this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories')
            ->assertOk()
            ->json('data');
        $categoryNames = collect($categories)->pluck('name')->all();
        $this->assertContains('Dresses', $categoryNames);
        $this->assertNotContains('Wigs', $categoryNames);

        $products = $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products')
            ->assertOk()
            ->json('data');
        $slugs = collect($products)->pluck('slug')->all();
        $this->assertContains($zionProduct->slug, $slugs);
        $this->assertNotContains($roviProduct->slug, $slugs);
        $this->assertNotContains($chinaProduct->slug, $slugs);
        $this->assertNotContains($draft->slug, $slugs);

        $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products/'.$roviProduct->slug)
            ->assertNotFound();

        $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products/'.$zionProduct->slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $zionProduct->slug);
    }

    public function test_logo_fallback_fields_and_empty_store_state(): void
    {
        $store = $this->stores->create([
            'code' => 'EMPTY',
            'name' => 'Empty Boutique',
            'slug' => 'empty-boutique',
            'description' => 'No products yet',
            'logo_path' => null,
            'is_active' => true,
            'storefront_enabled' => true,
            'storefront_visible' => true,
            'storefront_sort_order' => 1,
        ]);

        $this->getJson('/api/v1/storefront/tz/stores/empty-boutique')
            ->assertOk()
            ->assertJsonPath('data.slug', $store->slug)
            ->assertJsonPath('data.logo_url', null);

        $this->getJson('/api/v1/storefront/tz/stores/empty-boutique/products')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_store_isolation_for_category_filter(): void
    {
        $this->seed(StoreSeeder::class);
        $this->seed(TzStoreCategorySeeder::class);

        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();
        $peachy = Store::query()->where('slug', 'peachy-lingerie')->firstOrFail();
        $dressCat = Category::query()->where('store_id', $zion->id)->where('name', 'Dresses')->firstOrFail();
        $braCat = Category::query()->where('store_id', $peachy->id)->where('name', 'Bras')->firstOrFail();

        $this->makeProduct($zion, $dressCat, 'zion-dress-a', $this->tz);
        $this->makeProduct($peachy, $braCat, 'peachy-bra-a', $this->tz);

        $zionOnly = $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products?category='.$dressCat->slug)
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $zionOnly);
        $this->assertSame('zion-dress-a', $zionOnly[0]['slug']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProduct(
        ?Store $store,
        ?Category $category,
        string $slug,
        CommerceChannel $channel,
        array $overrides = [],
    ): Product {
        return Product::factory()->create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'store_id' => $store?->id,
            'category_id' => $category?->id,
            'commerce_channel_id' => $channel->id,
            'fulfillment_source' => $channel->code === CommerceChannelCode::TzLocal->value
                ? CommerceChannelCode::TzLocal->fulfillmentSource()
                : CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 25000,
        ], $overrides));
    }
}
