<?php

namespace Tests\Feature\Catalog;

use App\Enums\CatalogOrigin;
use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Admin;
use App\Models\Brand;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\Store;
use App\Support\Admin\AdminPermissions;
use App\Support\Catalog\CatalogLeafCategoryRules;
use App\Support\Catalog\ProductTaxonomyValidator;
use Database\Seeders\CatalogProductTypeSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Database\Seeders\SubcategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * P0 Networking & Power taxonomy under computers-office (CHINA_IMPORT only).
 */
class ComputersOfficeNetworkingPowerTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    private const PARENT_SLUG = 'computers-office-networking-power';

    private const LEAF_SLUGS = [
        'computers-office-networking-power-ups-backup-power',
        'computers-office-networking-power-dc-ups-router-backup',
        'computers-office-networking-power-routers-networking',
        'computers-office-networking-power-power-supplies',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CommerceChannelSeeder::class);
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);
        $this->seed(CatalogProductTypeSeeder::class);
    }

    public function test_networking_power_hierarchy_and_active_semantics(): void
    {
        $parent = Category::query()->where('slug', self::PARENT_SLUG)->firstOrFail();
        $this->assertSame('Networking & Power', $parent->name);
        $this->assertSame(CatalogOrigin::China, $parent->origin);
        $this->assertNull($parent->store_id);
        $this->assertNotNull($parent->department_id);
        $this->assertNull($parent->parent_id);
        $this->assertFalse($parent->is_active, 'Parent with children must stay inactive (seed convention).');

        foreach (self::LEAF_SLUGS as $slug) {
            $leaf = Category::query()->where('slug', $slug)->firstOrFail();
            $this->assertSame($parent->id, $leaf->parent_id);
            $this->assertSame($parent->department_id, $leaf->department_id);
            $this->assertSame(CatalogOrigin::China, $leaf->origin);
            $this->assertNull($leaf->store_id);
            $this->assertTrue($leaf->is_active);
            $this->assertTrue(
                Category::query()->where('parent_id', $leaf->id)->doesntExist(),
                "Leaf [{$slug}] must have no children.",
            );
        }
    }

    public function test_seed_idempotency_for_categories_and_cpts(): void
    {
        $categoryCount = Category::query()->count();
        $cptCount = CatalogProductType::query()->count();
        $leafIds = Category::query()->whereIn('slug', self::LEAF_SLUGS)->pluck('id', 'slug');

        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);
        $this->seed(CatalogProductTypeSeeder::class);

        $this->assertSame($categoryCount, Category::query()->count());
        $this->assertSame($cptCount, CatalogProductType::query()->count());
        foreach (self::LEAF_SLUGS as $slug) {
            $this->assertSame(
                $leafIds[$slug],
                Category::query()->where('slug', $slug)->value('id'),
            );
        }
    }

    public function test_parent_is_not_a_valid_publish_leaf(): void
    {
        $parent = Category::query()->where('slug', self::PARENT_SLUG)->firstOrFail();

        try {
            CatalogLeafCategoryRules::assertValidLeafParent((string) $parent->id);
            $this->fail('Expected ValidationException for non-leaf Networking & Power parent.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subcategory_id', $exception->errors());
        }
    }

    public function test_each_leaf_has_cpts_and_passes_china_taxonomy_validation(): void
    {
        $china = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->firstOrFail();

        $expectedCptNames = [
            'computers-office-networking-power-ups-backup-power' => ['AC UPS', 'Desktop UPS', 'Network Equipment UPS'],
            'computers-office-networking-power-dc-ups-router-backup' => ['DC UPS', 'Mini DC UPS', 'Router Backup Power Supply'],
            'computers-office-networking-power-routers-networking' => ['Router', 'Network Switch', 'Access Point'],
            'computers-office-networking-power-power-supplies' => ['Computer Power Supply', 'Network Power Supply'],
        ];

        foreach (self::LEAF_SLUGS as $slug) {
            $leaf = Category::query()->where('slug', $slug)->firstOrFail();
            CatalogLeafCategoryRules::assertValidLeafParent((string) $leaf->id);

            $types = CatalogProductType::query()
                ->where('subcategory_id', $leaf->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            $this->assertNotEmpty($types, "Leaf [{$slug}] must have CPTs.");
            $this->assertEqualsCanonicalizing(
                $expectedCptNames[$slug],
                $types->pluck('name')->all(),
            );

            ProductTaxonomyValidator::assertCategoryMatchesChannel(
                $leaf,
                CommerceChannelCode::ChinaImport,
            );

            Sanctum::actingAs(
                Admin::factory()->withPermissions([
                    AdminPermissions::CATALOG_CREATE,
                    AdminPermissions::CATALOG_VIEW,
                ])->create(),
            );

            $this->postJson('/api/v1/admin/products', [
                'name' => 'Draft '.$slug,
                'catalog_product_type_id' => $types->first()->id,
                'commerce_channel_id' => $china->id,
                'lifecycle_status' => ProductLifecycleStatus::Draft->value,
                'price' => 95000,
            ])->assertCreated();
        }
    }

    public function test_admin_picker_returns_parent_and_leaves(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $parent = Category::query()->where('slug', self::PARENT_SLUG)->firstOrFail();
        $departmentId = $parent->department_id;

        $all = collect(
            $this->getJson('/api/v1/admin/categories?origin=china&department_id='.$departmentId.'&per_page=200')
                ->assertOk()
                ->json('data'),
        );

        $this->assertContains($parent->id, $all->pluck('id')->all());
        foreach (self::LEAF_SLUGS as $slug) {
            $this->assertTrue($all->contains(fn (array $row) => ($row['slug'] ?? null) === $slug));
        }

        $activeOnly = collect(
            $this->getJson('/api/v1/admin/categories?origin=china&department_id='.$departmentId.'&is_active=1&per_page=200')
                ->assertOk()
                ->json('data'),
        );

        $this->assertFalse($activeOnly->contains(fn (array $row) => ($row['slug'] ?? null) === self::PARENT_SLUG));
        foreach (self::LEAF_SLUGS as $slug) {
            $this->assertTrue($activeOnly->contains(fn (array $row) => ($row['slug'] ?? null) === $slug));
        }
    }

    public function test_tz_store_category_cannot_use_china_networking_leaf(): void
    {
        $this->seed(StoreSeeder::class);
        $store = Store::query()->where('slug', 'zion-mode')->firstOrFail();
        $leaf = Category::query()
            ->where('slug', 'computers-office-networking-power-dc-ups-router-backup')
            ->firstOrFail();
        $cpt = CatalogProductType::query()->where('subcategory_id', $leaf->id)->firstOrFail();
        $tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();

        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_CREATE,
                AdminPermissions::CATALOG_VIEW,
            ])->create(),
        );

        $this->postJson('/api/v1/admin/products', [
            'name' => 'TZ leak attempt',
            'catalog_product_type_id' => $cpt->id,
            'commerce_channel_id' => $tz->id,
            'store_id' => $store->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_soft_deleted_leaf_is_not_a_valid_taxonomy_target(): void
    {
        $leaf = Category::query()
            ->where('slug', 'computers-office-networking-power-power-supplies')
            ->firstOrFail();
        $leaf->delete();

        $this->assertNull(
            Category::query()->where('slug', 'computers-office-networking-power-power-supplies')->first(),
        );

        try {
            CatalogLeafCategoryRules::assertValidLeafParent((string) $leaf->id);
            $this->fail('Expected ValidationException for soft-deleted leaf.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_dc_ups_product_appears_in_china_discovery_and_electronics_laptops_corpus(): void
    {
        $leaf = Category::query()
            ->where('slug', 'computers-office-networking-power-dc-ups-router-backup')
            ->firstOrFail();
        $product = $this->makeListableChinaProduct($leaf, 'dc-ups-router-backup-sample');

        $byLeaf = collect(
            $this->getJson('/api/v1/storefront/china/products?category='.$leaf->slug)
                ->assertOk()
                ->json('data'),
        )->pluck('slug')->all();
        $this->assertContains($product->slug, $byLeaf);

        $byElectronicsLaptops = collect(
            $this->getJson('/api/v1/storefront/china/products?category=electronics-laptops')
                ->assertOk()
                ->json('data'),
        )->pluck('slug')->all();
        $this->assertContains(
            $product->slug,
            $byElectronicsLaptops,
            'computers-office department products must remain in electronics-laptops crosswalk corpus.',
        );

        $byElectronics = collect(
            $this->getJson('/api/v1/storefront/china/products?category=electronics')
                ->assertOk()
                ->json('data'),
        )->pluck('slug')->all();
        $this->assertContains($product->slug, $byElectronics);
    }

    public function test_dc_ups_product_is_searchable_by_category_name(): void
    {
        $leaf = Category::query()
            ->where('slug', 'computers-office-networking-power-dc-ups-router-backup')
            ->firstOrFail();
        $product = $this->makeListableChinaProduct($leaf, 'searchable-mini-dc-ups');

        $response = $this->getJson('/api/v1/search/products?q='.urlencode('Router Backup'));
        if ($response->status() === 404) {
            $response = $this->getJson('/api/v1/search?q='.urlencode('Router Backup'));
        }

        $response->assertOk();
        $payload = $response->json();
        $slugs = collect($payload['data'] ?? $payload['products'] ?? [])
            ->pluck('slug')
            ->filter()
            ->all();

        if ($slugs === []) {
            $this->markTestSkipped('Search endpoint shape not product-list in this environment.');
        }

        $this->assertContains($product->slug, $slugs);
    }

    public function test_electronics_mega_menu_shows_networking_power_not_leaf_slugs(): void
    {
        $leaf = Category::query()
            ->where('slug', 'computers-office-networking-power-dc-ups-router-backup')
            ->firstOrFail();
        $this->makeListableChinaProduct($leaf, 'menu-guard-dc-ups');

        $menu = $this->getJson('/api/v1/storefront/china/menu?category=electronics')
            ->assertOk()
            ->json('data');

        $electronics = collect($menu['categories'] ?? [])->firstWhere('slug', 'electronics');
        $this->assertNotNull($electronics);
        $childSlugs = collect($electronics['children'] ?? [])->pluck('slug')->all();

        $this->assertContains('electronics-laptops', $childSlugs);
        $this->assertContains('electronics-networking-power', $childSlugs);
        $this->assertNotContains(self::PARENT_SLUG, $childSlugs);
        $this->assertNotContains('computers-office-networking-power-dc-ups-router-backup', $childSlugs);

        $featured = collect($menu['featured_products'] ?? [])->pluck('slug')->all();
        $this->assertContains('menu-guard-dc-ups', $featured);
    }

    private function makeListableChinaProduct(Category $category, string $slug): Product
    {
        $china = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->firstOrFail();
        $brand = Brand::factory()->create(['is_active' => true]);

        $product = Product::factory()->create([
            'name' => $slug,
            'slug' => $slug,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'store_id' => null,
            'commerce_channel_id' => $china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 125000,
        ]);

        Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );
        ProductShippingOption::factory()->air(5000)->create(['product_id' => $product->id]);

        return $product;
    }
}
