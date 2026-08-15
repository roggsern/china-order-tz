<?php

namespace Tests\Feature\Storefront;

use App\Enums\CatalogOrigin;
use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Product;
use App\Services\Storefront\CatalogNavigationCrosswalkResolver;
use App\Services\Storefront\ChinaStorefrontCatalog;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubcategorySeeder;
use Database\Support\CatalogBible;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogNavigationCrosswalkTest extends TestCase
{
    use RefreshDatabase;

    private CatalogNavigationCrosswalkResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CommerceChannelSeeder::class);
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);

        $this->resolver = app(CatalogNavigationCrosswalkResolver::class);
    }

    public function test_phones_department_products_make_electronics_phones_visible(): void
    {
        $iphoneCategory = Category::query()
            ->where('slug', 'phones-tablets-smartphones-iphones')
            ->firstOrFail();

        $this->createNavigationVisibleProduct($iphoneCategory, 'dept-iphone', null);

        $this->assertTrue($this->resolver->isBibleNodeVisible('electronics-phones'));
        $this->assertTrue($this->resolver->isBibleNodeVisible('electronics'));

        $navSlugs = app(ChinaStorefrontCatalog::class)
            ->navigationCategories()
            ->pluck('slug')
            ->all();

        $this->assertContains('electronics', $navSlugs);

        $electronics = app(ChinaStorefrontCatalog::class)
            ->navigationCategories()
            ->firstWhere('slug', 'electronics');

        $this->assertNotNull($electronics);
        $this->assertContains(
            'electronics-phones',
            $electronics->children->pluck('slug')->all(),
        );
    }

    public function test_womens_products_resolve_despite_slug_collision(): void
    {
        $midiDresses = Category::query()
            ->where('slug', 'womens-fashion-dresses-midi-dresses')
            ->firstOrFail();

        $this->assertSame(
            'womens-fashion-dresses',
            Category::query()->where('slug', 'womens-fashion-dresses')->value('slug'),
        );

        $this->createNavigationVisibleProduct($midiDresses, 'zara-midi-test', null);

        $this->assertTrue($this->resolver->isBibleNodeVisible('womens-fashion-dresses'));
        $this->assertTrue($this->resolver->isBibleNodeVisible('womens-fashion'));

        $womens = app(ChinaStorefrontCatalog::class)
            ->navigationCategories()
            ->firstWhere('slug', 'womens-fashion');

        $this->assertNotNull($womens);
        $this->assertContains(
            'womens-fashion-dresses',
            $womens->children->pluck('slug')->all(),
        );
    }

    public function test_professional_audio_resolves_via_electronics_audio_alias(): void
    {
        $activeSpeakers = Category::query()
            ->where('slug', 'professional-audio-pa-systems-active-speakers')
            ->firstOrFail();

        $this->createNavigationVisibleProduct($activeSpeakers, 'rcf-active-speaker', null);

        $this->assertTrue($this->resolver->isBibleNodeVisible('electronics-audio'));
        $this->assertTrue($this->resolver->isBibleNodeVisible('electronics'));
    }

    public function test_empty_mapped_categories_stay_hidden(): void
    {
        $this->assertFalse($this->resolver->isBibleNodeVisible('building-materials'));
        $this->assertFalse($this->resolver->isBibleNodeVisible('beauty'));
        $this->assertFalse($this->resolver->isBibleNodeVisible('home-care'));

        $navSlugs = app(ChinaStorefrontCatalog::class)
            ->navigationCategories()
            ->pluck('slug')
            ->all();

        $this->assertNotContains('building-materials', $navSlugs);
        $this->assertNotContains('beauty', $navSlugs);
        $this->assertNotContains('home-care', $navSlugs);
    }

    public function test_home_care_department_products_make_home_care_nav_root_visible(): void
    {
        $expectedRoots = collect(CatalogBible::categories())->pluck('slug')->all();
        $this->assertContains('home-care', $expectedRoots);
        $this->assertContains('mens-fashion', $expectedRoots);
        $this->assertContains('womens-fashion', $expectedRoots);
        $this->assertContains('electronics', $expectedRoots);
        $this->assertContains('beauty', $expectedRoots);

        $department = Department::query()->updateOrCreate(
            ['slug' => 'home-care'],
            [
                'name' => 'Home Care',
                'icon' => '🧹',
                'sort_order' => 99,
                'is_active' => true,
            ],
        );

        $pestControl = Category::query()->updateOrCreate(
            ['slug' => 'pest-control'],
            [
                'name' => 'Pest Control',
                'origin' => CatalogOrigin::China,
                'department_id' => $department->id,
                'parent_id' => null,
                'store_id' => null,
                'is_active' => true,
                'sort_order' => 10,
            ],
        );

        $this->createNavigationVisibleProduct($pestControl, 'home-care-insecticide', null);

        $this->assertTrue($this->resolver->isBibleNodeVisible('home-care'));

        $navSlugs = app(ChinaStorefrontCatalog::class)
            ->navigationCategories()
            ->pluck('slug')
            ->all();

        $this->assertContains('home-care', $navSlugs);

        foreach ($navSlugs as $slug) {
            $this->assertContains($slug, $expectedRoots);
        }

        $this->assertNotContains('home-appliances', $navSlugs);
        $this->assertNotContains('pet-supplies', $navSlugs);
        $this->assertNotContains('groceries', $navSlugs);

        $menuRoots = collect(
            $this->getJson('/api/v1/storefront/china/menu')
                ->assertOk()
                ->json('data.categories'),
        )->pluck('slug')->all();

        $this->assertContains('home-care', $menuRoots);
        foreach ($menuRoots as $slug) {
            $this->assertContains($slug, $expectedRoots);
        }

        $homeCare = app(ChinaStorefrontCatalog::class)
            ->navigationCategories()
            ->firstWhere('slug', 'home-care');
        $this->assertNotNull($homeCare);
        $this->assertContains('pest-control', $homeCare->children->pluck('slug')->all());
    }

    public function test_home_care_bible_rooted_children_appear_when_populated(): void
    {
        $department = Department::query()->updateOrCreate(
            ['slug' => 'home-care'],
            [
                'name' => 'Home Care',
                'icon' => '🧹',
                'sort_order' => 99,
                'is_active' => true,
            ],
        );

        $bibleRoot = Category::query()->updateOrCreate(
            ['slug' => 'home-care'],
            [
                'name' => 'Home Care',
                'origin' => CatalogOrigin::China,
                'department_id' => null,
                'parent_id' => null,
                'store_id' => null,
                'is_active' => true,
                'sort_order' => 50,
            ],
        );

        $pestControl = Category::query()->updateOrCreate(
            ['slug' => 'pest-control'],
            [
                'name' => 'Pest Control',
                'origin' => CatalogOrigin::China,
                'department_id' => $department->id,
                'parent_id' => $bibleRoot->id,
                'store_id' => null,
                'is_active' => true,
                'sort_order' => 10,
            ],
        );
        $cleaning = Category::query()->updateOrCreate(
            ['slug' => 'cleaning-hygiene'],
            [
                'name' => 'Cleaning & Hygiene',
                'origin' => CatalogOrigin::China,
                'department_id' => $department->id,
                'parent_id' => $bibleRoot->id,
                'store_id' => null,
                'is_active' => true,
                'sort_order' => 20,
            ],
        );
        $household = Category::query()->updateOrCreate(
            ['slug' => 'household-essentials'],
            [
                'name' => 'Household Essentials',
                'origin' => CatalogOrigin::China,
                'department_id' => $department->id,
                'parent_id' => $bibleRoot->id,
                'store_id' => null,
                'is_active' => true,
                'sort_order' => 30,
            ],
        );
        $spray = Category::query()->updateOrCreate(
            ['slug' => 'pest-control-aerosol-sprays'],
            [
                'name' => 'Aerosol Sprays',
                'origin' => CatalogOrigin::China,
                'department_id' => $department->id,
                'parent_id' => $pestControl->id,
                'store_id' => null,
                'is_active' => true,
                'sort_order' => 10,
            ],
        );
        $inactiveLeaf = Category::query()->updateOrCreate(
            ['slug' => 'smart-home-care'],
            [
                'name' => 'Smart Home Care',
                'origin' => CatalogOrigin::China,
                'department_id' => $department->id,
                'parent_id' => $bibleRoot->id,
                'store_id' => null,
                'is_active' => false,
                'sort_order' => 40,
            ],
        );

        // Direct child product → Pest Control visible.
        $this->createNavigationVisibleProduct($pestControl, 'pests-killer-direct', null);
        // Grandchild product → still surfaces Pest Control (already visible) and not Cleaning.
        $this->createNavigationVisibleProduct($spray, 'pests-killer-grandchild', null);
        // Empty Cleaning stays hidden.
        // Inactive Smart Home Care stays hidden even with a product.
        $this->createNavigationVisibleProduct($inactiveLeaf, 'smart-home-inactive-product', null);
        // Draft on Household does not populate.
        Product::factory()->create([
            'name' => 'Draft household',
            'slug' => 'home-care-draft-household',
            'category_id' => $household->id,
            'store_id' => null,
            'commerce_channel_id' => null,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'visibility' => ProductVisibility::Public,
            'price' => 15000,
        ]);
        // Product on Bible root only — does not invent a subcategory.
        $this->createNavigationVisibleProduct($bibleRoot, 'home-care-root-assigned', null);

        $homeCare = app(ChinaStorefrontCatalog::class)
            ->navigationCategories()
            ->firstWhere('slug', 'home-care');

        $this->assertNotNull($homeCare);
        $childSlugs = $homeCare->children->pluck('slug')->all();
        $this->assertContains('pest-control', $childSlugs);
        $this->assertNotContains('cleaning-hygiene', $childSlugs);
        $this->assertNotContains('household-essentials', $childSlugs);
        $this->assertNotContains('smart-home-care', $childSlugs);
        $this->assertNotContains('home-care', $childSlugs);
        $this->assertNotContains('pest-control-aerosol-sprays', $childSlugs);

        $menu = $this->getJson('/api/v1/storefront/china/menu?category=home-care')
            ->assertOk()
            ->json('data');
        $menuHome = collect($menu['categories'])->firstWhere('slug', 'home-care');
        $this->assertNotNull($menuHome);
        $this->assertContains(
            'pest-control',
            collect($menuHome['children'] ?? [])->pluck('slug')->all(),
        );
    }

    public function test_home_care_soft_deleted_bible_rooted_child_is_omitted(): void
    {
        $department = Department::query()->updateOrCreate(
            ['slug' => 'home-care'],
            [
                'name' => 'Home Care',
                'icon' => '🧹',
                'sort_order' => 99,
                'is_active' => true,
            ],
        );
        $bibleRoot = Category::query()->updateOrCreate(
            ['slug' => 'home-care'],
            [
                'name' => 'Home Care',
                'origin' => CatalogOrigin::China,
                'department_id' => null,
                'parent_id' => null,
                'store_id' => null,
                'is_active' => true,
                'sort_order' => 50,
            ],
        );
        $pestControl = Category::query()->updateOrCreate(
            ['slug' => 'pest-control'],
            [
                'name' => 'Pest Control',
                'origin' => CatalogOrigin::China,
                'department_id' => $department->id,
                'parent_id' => $bibleRoot->id,
                'store_id' => null,
                'is_active' => true,
                'sort_order' => 10,
            ],
        );
        $this->createNavigationVisibleProduct($pestControl, 'soft-delete-pest-product', null);
        $pestControl->delete();

        $homeCare = app(ChinaStorefrontCatalog::class)
            ->navigationCategories()
            ->firstWhere('slug', 'home-care');

        // Root may still be visible via soft-deleted category IDs still in department
        // mapping cache... actually SoftDeletes excludes pest-control from categoryIdsForDepartmentSlug
        // so root may disappear. Either way Pest Control must not appear as a child.
        if ($homeCare !== null) {
            $this->assertNotContains(
                'pest-control',
                $homeCare->children->pluck('slug')->all(),
            );
        } else {
            $this->assertFalse($this->resolver->isBibleNodeVisible('home-care'));
        }
    }

    public function test_orphan_category_is_excluded_from_crosswalk_visibility(): void
    {
        $orphan = Category::factory()->create([
            'name' => 'Consequatur Et',
            'slug' => 'consequatur-et',
            'origin' => CatalogOrigin::China,
            'parent_id' => null,
            'store_id' => null,
            'is_active' => true,
        ]);

        $china = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->firstOrFail();

        $this->createNavigationVisibleProduct($orphan, 'orphan-china-product', $china);

        $this->assertFalse($this->resolver->isBibleNodeVisible('electronics'));
        $this->assertFalse($this->resolver->isBibleNodeVisible('mens-fashion'));

        $navSlugs = app(ChinaStorefrontCatalog::class)
            ->navigationCategories()
            ->pluck('slug')
            ->all();

        $this->assertNotContains('consequatur-et', $navSlugs);
    }

    public function test_china_import_channel_products_count_for_navigation_visibility(): void
    {
        $phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();
        $china = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->firstOrFail();

        $this->createNavigationVisibleProduct($phones, 'china-import-phone', $china);

        $this->assertTrue($this->resolver->isBibleNodeVisible('electronics-phones'));
    }

    public function test_category_ids_for_bible_slug_includes_descendants(): void
    {
        $ids = $this->resolver->categoryIdsForBibleSlug('womens-fashion-dresses');
        $midi = Category::query()
            ->where('slug', 'womens-fashion-dresses-midi-dresses')
            ->value('id');

        $this->assertContains($midi, $ids);
    }

    public function test_department_mapped_root_shows_all_populated_subcategories(): void
    {
        $midi = Category::query()->where('slug', 'womens-fashion-dresses-midi-dresses')->firstOrFail();
        $blouses = Category::query()->where('slug', 'womens-fashion-tops-blouses')->firstOrFail();
        $palazzo = Category::query()->where('slug', 'womens-fashion-pants-palazzo-pants')->firstOrFail();
        $emptyShoes = Category::query()->where('slug', 'womens-fashion-shoes')->firstOrFail();

        $this->createNavigationVisibleProduct($midi, 'wf-midi-visible', null);
        $this->createNavigationVisibleProduct($blouses, 'wf-blouse-visible', null);
        $this->createNavigationVisibleProduct($palazzo, 'wf-palazzo-visible', null);

        $womens = app(ChinaStorefrontCatalog::class)
            ->navigationCategories()
            ->firstWhere('slug', 'womens-fashion');

        $this->assertNotNull($womens);
        $childSlugs = $womens->children->pluck('slug')->all();

        $this->assertContains('womens-fashion-dresses', $childSlugs);
        $this->assertContains('womens-fashion-tops', $childSlugs);
        $this->assertContains('womens-fashion-pants', $childSlugs);
        $this->assertNotContains('womens-fashion-shoes', $childSlugs);
        $this->assertNotContains($emptyShoes->slug.'-high-heels', $childSlugs);
        $this->assertGreaterThanOrEqual(3, count($childSlugs));
    }

    public function test_department_child_branch_product_discovery_includes_descendants(): void
    {
        $jeans = Category::query()->where('slug', 'womens-fashion-pants-jeans')->firstOrFail();
        $china = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->firstOrFail();
        $this->createListableChinaProduct($jeans, 'wf-jeans-branch', $china);

        $ids = $this->resolver->resolveChinaCategoryBranchIds('womens-fashion-pants');
        $this->assertContains((string) $jeans->id, $ids);

        $products = $this->getJson('/api/v1/storefront/china/products?category=womens-fashion-pants')
            ->assertOk()
            ->json('data');

        $this->assertContains('wf-jeans-branch', collect($products)->pluck('slug')->all());
    }

    public function test_draft_product_does_not_populate_department_subcategory(): void
    {
        $pencil = Category::query()->where('slug', 'womens-fashion-skirts-pencil-skirts')->firstOrFail();

        Product::factory()->create([
            'name' => 'Draft skirt',
            'slug' => 'wf-draft-skirt',
            'category_id' => $pencil->id,
            'store_id' => null,
            'commerce_channel_id' => null,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'visibility' => ProductVisibility::Public,
            'price' => 50000,
        ]);

        $womens = app(ChinaStorefrontCatalog::class)
            ->navigationCategories()
            ->firstWhere('slug', 'womens-fashion');

        $this->assertTrue(
            $womens === null || ! $womens->children->contains('slug', 'womens-fashion-skirts'),
        );
    }

    public function test_soft_deleted_department_category_is_omitted_from_nav_children(): void
    {
        $midi = Category::query()->where('slug', 'womens-fashion-dresses-midi-dresses')->firstOrFail();
        $this->createNavigationVisibleProduct($midi, 'wf-soft-delete-dress', null);

        $dresses = Category::query()->where('slug', 'womens-fashion-dresses')->firstOrFail();
        $dresses->delete();

        $womens = app(ChinaStorefrontCatalog::class)
            ->navigationCategories()
            ->firstWhere('slug', 'womens-fashion');

        $this->assertNotNull($womens);
        $this->assertNotContains(
            'womens-fashion-dresses',
            $womens->children->pluck('slug')->all(),
        );
    }

    public function test_tz_store_category_does_not_appear_in_china_department_children(): void
    {
        $this->seed(\Database\Seeders\StoreSeeder::class);
        $store = \App\Models\Store::query()->where('slug', 'zion-mode')->firstOrFail();
        $dept = \App\Models\Department::query()->where('slug', 'womens-fashion')->firstOrFail();

        Category::factory()->create([
            'name' => 'TZ Pants Leak',
            'slug' => 'zion-mode-pants-leak',
            'origin' => CatalogOrigin::Tz,
            'store_id' => $store->id,
            'department_id' => $dept->id,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $midi = Category::query()->where('slug', 'womens-fashion-dresses-midi-dresses')->firstOrFail();
        $this->createNavigationVisibleProduct($midi, 'wf-tz-isolation-dress', null);

        $womens = app(ChinaStorefrontCatalog::class)
            ->navigationCategories()
            ->firstWhere('slug', 'womens-fashion');

        $this->assertNotNull($womens);
        $this->assertNotContains(
            'zion-mode-pants-leak',
            $womens->children->pluck('slug')->all(),
        );
    }

    public function test_menu_featured_products_are_department_scoped_and_bounded(): void
    {
        $midi = Category::query()->where('slug', 'womens-fashion-dresses-midi-dresses')->firstOrFail();
        $phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();
        $china = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->firstOrFail();

        for ($i = 1; $i <= 6; $i++) {
            $this->createListableChinaProduct($midi, "wf-featured-dress-{$i}", $china);
        }
        $this->createListableChinaProduct($phones, 'electronics-featured-phone', $china);

        $menu = $this->getJson('/api/v1/storefront/china/menu?category=womens-fashion')
            ->assertOk()
            ->json('data');

        $featured = collect($menu['featured_products'] ?? []);
        $this->assertLessThanOrEqual(4, $featured->count());
        $this->assertNotEmpty($featured);
        $this->assertNotContains('electronics-featured-phone', $featured->pluck('slug')->all());
        foreach ($featured as $tile) {
            $this->assertStringStartsWith('wf-featured-dress-', $tile['slug']);
        }
    }

    public function test_navigation_api_uses_crosswalk_filtered_tree(): void
    {
        $iphoneCategory = Category::query()
            ->where('slug', 'phones-tablets-smartphones-iphones')
            ->firstOrFail();

        $this->createNavigationVisibleProduct($iphoneCategory, 'api-nav-iphone', null);

        $response = $this->getJson('/api/v1/storefront/china/categories')
            ->assertOk()
            ->json('data');

        $rootSlugs = collect($response)->pluck('slug')->all();
        $this->assertContains('electronics', $rootSlugs);
        $this->assertNotContains('building-materials', $rootSlugs);

        foreach ($rootSlugs as $slug) {
            $this->assertContains($slug, collect(CatalogBible::categories())->pluck('slug')->all());
        }
    }

    private function createNavigationVisibleProduct(
        Category $category,
        string $slug,
        ?CommerceChannel $channel,
    ): Product {
        return Product::factory()->create([
            'name' => $slug,
            'slug' => $slug,
            'category_id' => $category->id,
            'store_id' => null,
            'commerce_channel_id' => $channel?->id,
            'fulfillment_source' => $channel !== null
                ? CommerceChannelCode::ChinaImport->fulfillmentSource()
                : null,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 120000,
        ]);
    }

    private function createListableChinaProduct(
        Category $category,
        string $slug,
        CommerceChannel $channel,
    ): Product {
        $product = Product::factory()->create([
            'name' => $slug,
            'slug' => $slug,
            'category_id' => $category->id,
            'store_id' => null,
            'commerce_channel_id' => $channel->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 120000,
        ]);

        \App\Models\Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );
        \App\Models\ProductShippingOption::factory()->air(5000)->create(['product_id' => $product->id]);

        return $product;
    }
}
