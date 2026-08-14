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
        $this->assertNotNull(
            collect($response->json('data'))->firstWhere('slug', 'zion-mode')['logo_url'] ?? null,
        );
        $this->assertNotNull(
            collect($response->json('data'))->firstWhere('slug', 'peachy-lingerie')['logo_url'] ?? null,
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

    public function test_category_deep_link_resolves_root_child_and_grandchild(): void
    {
        $this->seed(StoreSeeder::class);
        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();

        $pants = Category::factory()->forStore($zion)->create([
            'name' => 'Pants',
            'slug' => 'zion-mode-womens-fashion-pants-dl',
            'parent_id' => null,
            'is_active' => true,
        ]);
        $palazzo = Category::factory()->forStore($zion)->create([
            'name' => 'Palazzo Pants',
            'slug' => 'zion-mode-womens-fashion-pants-palazzo-pants-dl',
            'parent_id' => $pants->id,
            'is_active' => true,
        ]);
        $wideLeg = Category::factory()->forStore($zion)->create([
            'name' => 'Wide Leg',
            'slug' => 'zion-mode-womens-fashion-pants-palazzo-pants-wide-leg-dl',
            'parent_id' => $palazzo->id,
            'is_active' => true,
        ]);

        $root = $this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories/'.$pants->slug)
            ->assertOk()
            ->json('data');
        $this->assertSame($pants->id, $root['id']);
        $this->assertSame('Pants', $root['name']);
        $this->assertSame([], $root['ancestors']);

        $child = $this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories/'.$palazzo->slug)
            ->assertOk()
            ->json('data');
        $this->assertSame($palazzo->id, $child['id']);
        $this->assertSame('Palazzo Pants', $child['name']);
        $this->assertCount(1, $child['ancestors']);
        $this->assertSame($pants->slug, $child['ancestors'][0]['slug']);
        $this->assertSame('Pants', $child['ancestors'][0]['name']);

        $grand = $this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories/'.$wideLeg->slug)
            ->assertOk()
            ->json('data');
        $this->assertSame($wideLeg->id, $grand['id']);
        $this->assertSame('Wide Leg', $grand['name']);
        $this->assertCount(2, $grand['ancestors']);
        $this->assertSame($pants->slug, $grand['ancestors'][0]['slug']);
        $this->assertSame($palazzo->slug, $grand['ancestors'][1]['slug']);
    }

    public function test_child_category_page_includes_branch_excludes_siblings(): void
    {
        $this->seed(StoreSeeder::class);
        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();

        $pants = Category::factory()->forStore($zion)->create([
            'name' => 'Pants',
            'slug' => 'zion-mode-pants-branch-dl',
            'parent_id' => null,
            'is_active' => true,
        ]);
        $palazzo = Category::factory()->forStore($zion)->create([
            'name' => 'Palazzo Pants',
            'slug' => 'zion-mode-pants-palazzo-branch-dl',
            'parent_id' => $pants->id,
            'is_active' => true,
        ]);
        $jeans = Category::factory()->forStore($zion)->create([
            'name' => 'Jeans',
            'slug' => 'zion-mode-pants-jeans-branch-dl',
            'parent_id' => $pants->id,
            'is_active' => true,
        ]);
        $wideLeg = Category::factory()->forStore($zion)->create([
            'name' => 'Wide Leg',
            'slug' => 'zion-mode-pants-palazzo-wide-branch-dl',
            'parent_id' => $palazzo->id,
            'is_active' => true,
        ]);

        $this->makeProduct($zion, $palazzo, 'palazzo-direct', $this->tz);
        $this->makeProduct($zion, $wideLeg, 'palazzo-descendant', $this->tz);
        $this->makeProduct($zion, $jeans, 'jeans-sibling', $this->tz);
        $this->makeProduct($zion, $pants, 'pants-root-direct', $this->tz);

        $childPage = $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products?category='.$palazzo->slug)
            ->assertOk()
            ->json('data');
        $childSlugs = collect($childPage)->pluck('slug')->sort()->values()->all();
        $this->assertSame(['palazzo-descendant', 'palazzo-direct'], $childSlugs);

        $parentPage = $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products?category='.$pants->slug)
            ->assertOk()
            ->json('data');
        $parentSlugs = collect($parentPage)->pluck('slug')->sort()->values()->all();
        $this->assertSame(
            ['jeans-sibling', 'palazzo-descendant', 'palazzo-direct', 'pants-root-direct'],
            $parentSlugs
        );
    }

    public function test_category_deep_link_rejects_wrong_store_inactive_soft_deleted_and_unknown(): void
    {
        $this->seed(StoreSeeder::class);
        $this->seed(TzStoreCategorySeeder::class);

        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();
        $peachy = Store::query()->where('slug', 'peachy-lingerie')->firstOrFail();
        $bra = Category::query()->where('store_id', $peachy->id)->where('name', 'Bras')->firstOrFail();

        $inactive = Category::factory()->forStore($zion)->create([
            'name' => 'Inactive Leaf',
            'slug' => 'zion-mode-inactive-leaf-dl',
            'parent_id' => null,
            'is_active' => false,
        ]);
        $deleted = Category::factory()->forStore($zion)->create([
            'name' => 'Deleted Leaf',
            'slug' => 'zion-mode-deleted-leaf-dl',
            'parent_id' => null,
            'is_active' => true,
        ]);
        $deleted->delete();

        $this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories/'.$bra->slug)
            ->assertNotFound();
        $this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories/'.$inactive->slug)
            ->assertNotFound();
        $this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories/'.$deleted->slug)
            ->assertNotFound();
        $this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories/does-not-exist-anywhere')
            ->assertNotFound();
    }

    public function test_direct_root_category_product_appears_in_navigation_and_page(): void
    {
        $this->seed(StoreSeeder::class);
        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();

        $dresses = Category::factory()->forStore($zion)->create([
            'name' => 'Dresses',
            'slug' => 'zion-mode-dresses-nav',
            'parent_id' => null,
            'is_active' => true,
        ]);
        Category::factory()->forStore($zion)->create([
            'name' => 'Empty Shoes',
            'slug' => 'zion-mode-empty-shoes-nav',
            'parent_id' => null,
            'is_active' => true,
        ]);

        $product = $this->makeProduct($zion, $dresses, 'stretch-pencil-dresses-nav', $this->tz);

        $categories = collect($this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories')
            ->assertOk()
            ->json('data'));

        $this->assertNotNull($categories->firstWhere('slug', $dresses->slug));
        $this->assertNull($categories->firstWhere('slug', 'zion-mode-empty-shoes-nav'));

        $page = $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products?category='.$dresses->slug)
            ->assertOk()
            ->assertJsonPath('data.0.slug', $product->slug)
            ->json('data');

        $this->assertCount(1, $page);
        $this->assertArrayHasKey('is_purchasable', $page[0]);
        $this->assertArrayHasKey('availability_status', $page[0]);
        $this->assertArrayHasKey('requires_variant_selection', $page[0]);
        $this->assertArrayHasKey('price', $page[0]);
        $this->assertFalse($page[0]['requires_variant_selection']);
    }

    public function test_parent_category_includes_child_assigned_products(): void
    {
        $this->seed(StoreSeeder::class);
        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();

        $pants = Category::factory()->forStore($zion)->create([
            'name' => 'Pants',
            'slug' => 'zion-mode-womens-fashion-pants-nav',
            'parent_id' => null,
            'is_active' => true,
        ]);
        $palazzo = Category::factory()->forStore($zion)->child($pants)->create([
            'name' => 'Palazzo Pants',
            'slug' => 'zion-mode-womens-fashion-pants-palazzo-pants-nav',
            'is_active' => true,
        ]);

        $product = $this->makeProduct($zion, $palazzo, 'high-waist-stretch-palazzo-pants-nav', $this->tz);

        $categories = collect($this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories')
            ->assertOk()
            ->json('data'));
        $this->assertNotNull($categories->firstWhere('slug', $pants->slug));

        $page = $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products?category='.$pants->slug)
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $page);
        $this->assertSame($product->slug, $page[0]['slug']);
    }

    public function test_cardigans_parent_includes_nested_child_product(): void
    {
        $this->seed(StoreSeeder::class);
        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();

        $cardigansSweaters = Category::factory()->forStore($zion)->create([
            'name' => 'Cardigans & Sweaters',
            'slug' => 'zion-mode-cardigans-nav',
            'parent_id' => null,
            'is_active' => true,
        ]);
        $cardigans = Category::factory()->forStore($zion)->child($cardigansSweaters)->create([
            'name' => 'Cardigans',
            'slug' => 'zion-mode-cardigans-cardigan-nav',
            'is_active' => true,
        ]);

        $product = $this->makeProduct($zion, $cardigans, 'cardigan-sweaters-nav', $this->tz);

        $this->assertNotNull(
            collect($this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories')->json('data'))
                ->firstWhere('slug', $cardigansSweaters->slug),
        );

        $page = $this->getJson(
            '/api/v1/storefront/tz/stores/zion-mode/products?category='.$cardigansSweaters->slug,
        )->assertOk()->json('data');

        $this->assertCount(1, $page);
        $this->assertSame($product->slug, $page[0]['slug']);
    }

    public function test_empty_root_absent_from_navigation_but_direct_url_returns_empty_corpus(): void
    {
        $this->seed(StoreSeeder::class);
        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();

        $empty = Category::factory()->forStore($zion)->create([
            'name' => 'Empty Root',
            'slug' => 'zion-mode-empty-root-nav',
            'parent_id' => null,
            'is_active' => true,
        ]);
        $child = Category::factory()->forStore($zion)->child($empty)->create([
            'name' => 'Empty Child',
            'slug' => 'zion-mode-empty-child-nav',
            'is_active' => true,
        ]);

        $this->assertNull(
            collect($this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories')->json('data'))
                ->firstWhere('slug', $empty->slug),
        );

        $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products?category='.$empty->slug)
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products?category='.$child->slug)
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_mixed_direct_and_descendant_products_returned_once(): void
    {
        $this->seed(StoreSeeder::class);
        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();

        $parent = Category::factory()->forStore($zion)->create([
            'name' => 'Mixed Parent',
            'slug' => 'zion-mode-mixed-parent',
            'parent_id' => null,
            'is_active' => true,
        ]);
        $child = Category::factory()->forStore($zion)->child($parent)->create([
            'name' => 'Mixed Child',
            'slug' => 'zion-mode-mixed-child',
            'is_active' => true,
        ]);

        $direct = $this->makeProduct($zion, $parent, 'mixed-direct-product', $this->tz);
        $nested = $this->makeProduct($zion, $child, 'mixed-nested-product', $this->tz);

        $slugs = collect(
            $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products?category='.$parent->slug)
                ->assertOk()
                ->json('data'),
        )->pluck('slug')->all();

        $this->assertEqualsCanonicalizing([$direct->slug, $nested->slug], $slugs);
        $this->assertCount(2, $slugs);
    }

    public function test_multi_level_descendant_product_included_on_root_page(): void
    {
        $this->seed(StoreSeeder::class);
        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();

        $parent = Category::factory()->forStore($zion)->create([
            'name' => 'Deep Parent',
            'slug' => 'zion-mode-deep-parent',
            'parent_id' => null,
            'is_active' => true,
        ]);
        $child = Category::factory()->forStore($zion)->child($parent)->create([
            'name' => 'Deep Child',
            'slug' => 'zion-mode-deep-child',
            'is_active' => true,
        ]);
        $grandchild = Category::factory()->forStore($zion)->child($child)->create([
            'name' => 'Deep Grandchild',
            'slug' => 'zion-mode-deep-grandchild',
            'is_active' => true,
        ]);

        $product = $this->makeProduct($zion, $grandchild, 'deep-grandchild-product', $this->tz);

        $this->assertNotNull(
            collect($this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories')->json('data'))
                ->firstWhere('slug', $parent->slug),
        );

        $page = $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products?category='.$parent->slug)
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $page);
        $this->assertSame($product->slug, $page[0]['slug']);
    }

    public function test_store_b_branch_does_not_populate_store_a_navigation_or_page(): void
    {
        $this->seed(StoreSeeder::class);
        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();
        $peachy = Store::query()->where('slug', 'peachy-lingerie')->firstOrFail();

        $zionPants = Category::factory()->forStore($zion)->create([
            'name' => 'Pants',
            'slug' => 'zion-mode-isolation-pants',
            'parent_id' => null,
            'is_active' => true,
        ]);
        $peachyPants = Category::factory()->forStore($peachy)->create([
            'name' => 'Pants',
            'slug' => 'peachy-isolation-pants',
            'parent_id' => null,
            'is_active' => true,
        ]);
        $peachyChild = Category::factory()->forStore($peachy)->child($peachyPants)->create([
            'name' => 'Palazzo',
            'slug' => 'peachy-isolation-palazzo',
            'is_active' => true,
        ]);

        $this->makeProduct($peachy, $peachyChild, 'peachy-only-palazzo', $this->tz);

        $zionCats = collect($this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories')->json('data'));
        $this->assertNull($zionCats->firstWhere('slug', $zionPants->slug));

        $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products?category='.$zionPants->slug)
            ->assertOk()
            ->assertJsonPath('data', []);

        $peachyPage = $this->getJson('/api/v1/storefront/tz/stores/peachy-lingerie/products?category='.$peachyPants->slug)
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $peachyPage);
        $this->assertSame('peachy-only-palazzo', $peachyPage[0]['slug']);
    }

    public function test_inactive_and_soft_deleted_categories_do_not_populate_parent_branch(): void
    {
        $this->seed(StoreSeeder::class);
        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();

        $parent = Category::factory()->forStore($zion)->create([
            'name' => 'Invalid Branch Parent',
            'slug' => 'zion-mode-invalid-branch-parent',
            'parent_id' => null,
            'is_active' => true,
        ]);
        $inactiveChild = Category::factory()->forStore($zion)->child($parent)->create([
            'name' => 'Inactive Child',
            'slug' => 'zion-mode-inactive-child',
            'is_active' => false,
        ]);
        $deletedChild = Category::factory()->forStore($zion)->child($parent)->create([
            'name' => 'Deleted Child',
            'slug' => 'zion-mode-deleted-child',
            'is_active' => true,
        ]);

        $this->makeProduct($zion, $inactiveChild, 'inactive-child-product', $this->tz);
        $this->makeProduct($zion, $deletedChild, 'deleted-child-product', $this->tz);
        $deletedChild->delete();

        $this->assertNull(
            collect($this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories')->json('data'))
                ->firstWhere('slug', $parent->slug),
        );

        $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products?category='.$parent->slug)
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_draft_product_does_not_populate_category_navigation(): void
    {
        $this->seed(StoreSeeder::class);
        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();

        $root = Category::factory()->forStore($zion)->create([
            'name' => 'Draft Only Root',
            'slug' => 'zion-mode-draft-only-root',
            'parent_id' => null,
            'is_active' => true,
        ]);
        $child = Category::factory()->forStore($zion)->child($root)->create([
            'name' => 'Draft Only Child',
            'slug' => 'zion-mode-draft-only-child',
            'is_active' => true,
        ]);

        $this->makeProduct($zion, $child, 'draft-only-product', $this->tz, [
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $this->assertNull(
            collect($this->getJson('/api/v1/storefront/tz/stores/zion-mode/categories')->json('data'))
                ->firstWhere('slug', $root->slug),
        );

        $this->getJson('/api/v1/storefront/tz/stores/zion-mode/products?category='.$root->slug)
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_show_store_embeds_product_aware_navigable_categories(): void
    {
        $this->seed(StoreSeeder::class);
        $zion = Store::query()->where('slug', 'zion-mode')->firstOrFail();

        $populated = Category::factory()->forStore($zion)->create([
            'name' => 'Show Store Populated',
            'slug' => 'zion-mode-show-store-populated',
            'parent_id' => null,
            'is_active' => true,
        ]);
        Category::factory()->forStore($zion)->create([
            'name' => 'Show Store Empty',
            'slug' => 'zion-mode-show-store-empty',
            'parent_id' => null,
            'is_active' => true,
        ]);
        $this->makeProduct($zion, $populated, 'show-store-product', $this->tz);

        $embedded = collect(
            $this->getJson('/api/v1/storefront/tz/stores/zion-mode')
                ->assertOk()
                ->json('data.categories'),
        );

        $this->assertNotNull($embedded->firstWhere('slug', $populated->slug));
        $this->assertNull($embedded->firstWhere('slug', 'zion-mode-show-store-empty'));
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
