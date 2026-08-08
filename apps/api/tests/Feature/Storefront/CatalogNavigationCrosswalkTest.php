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
}
