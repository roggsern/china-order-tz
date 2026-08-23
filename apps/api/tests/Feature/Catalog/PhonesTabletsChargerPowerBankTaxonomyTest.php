<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Support\Catalog\CatalogLeafCategoryRules;
use App\Support\Catalog\MobileAccessoriesTaxonomy;
use App\Enums\CatalogOrigin;
use Database\Seeders\CatalogProductTypeSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\SubcategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Canonical nested Chargers / Power Banks under Phone Accessories;
 * flat department starters must not be recreated by CategorySeeder.
 */
class PhonesTabletsChargerPowerBankTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    private const CANONICAL_CHARGERS = 'phones-tablets-phone-accessories-chargers';

    private const CANONICAL_POWER_BANKS = 'phones-tablets-phone-accessories-power-banks';

    private const LEGACY_FLAT_CHARGERS = 'phones-tablets-chargers';

    private const LEGACY_FLAT_POWER_BANKS = 'phones-tablets-power-banks';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);
        $this->seed(CatalogProductTypeSeeder::class);
    }

    public function test_charger_canonical_taxonomy_is_deterministic(): void
    {
        $accessories = Category::query()->where('slug', 'phones-tablets-phone-accessories')->firstOrFail();
        $chargers = Category::query()->where('slug', self::CANONICAL_CHARGERS)->firstOrFail();

        $this->assertSame($accessories->id, $chargers->parent_id);
        $this->assertTrue($chargers->is_active);
        $this->assertSame($accessories->department_id, $chargers->department_id);

        $cpt = CatalogProductType::query()
            ->where('slug', self::CANONICAL_CHARGERS.'-charger')
            ->firstOrFail();
        $this->assertSame($chargers->id, $cpt->subcategory_id);

        $this->assertNull(Category::query()->where('slug', self::LEGACY_FLAT_CHARGERS)->first());
    }

    public function test_power_bank_canonical_taxonomy_is_deterministic(): void
    {
        $accessories = Category::query()->where('slug', 'phones-tablets-phone-accessories')->firstOrFail();
        $powerBanks = Category::query()->where('slug', self::CANONICAL_POWER_BANKS)->firstOrFail();

        $this->assertSame($accessories->id, $powerBanks->parent_id);
        $this->assertTrue($powerBanks->is_active);
        $this->assertSame($accessories->department_id, $powerBanks->department_id);

        $cpt = CatalogProductType::query()
            ->where('slug', self::CANONICAL_POWER_BANKS.'-power-bank')
            ->firstOrFail();
        $this->assertSame($powerBanks->id, $cpt->subcategory_id);

        $this->assertNull(Category::query()->where('slug', self::LEGACY_FLAT_POWER_BANKS)->first());
    }

    public function test_legacy_flat_charger_power_bank_nodes_are_not_valid_product_targets_when_present(): void
    {
        $departmentId = Category::query()
            ->where('slug', self::CANONICAL_CHARGERS)
            ->value('department_id');

        $legacyChargers = Category::query()->create([
            'department_id' => $departmentId,
            'parent_id' => null,
            'origin' => CatalogOrigin::China,
            'name' => 'Chargers',
            'slug' => self::LEGACY_FLAT_CHARGERS,
            'sort_order' => 99,
            'is_active' => false,
        ]);

        $legacyPowerBanks = Category::query()->create([
            'department_id' => $departmentId,
            'parent_id' => null,
            'origin' => CatalogOrigin::China,
            'name' => 'Power Banks',
            'slug' => self::LEGACY_FLAT_POWER_BANKS,
            'sort_order' => 100,
            'is_active' => false,
        ]);

        try {
            CatalogLeafCategoryRules::assertValidLeafParent($legacyChargers->id);
            $this->fail('Inactive legacy chargers leaf must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subcategory_id', $exception->errors());
        }

        try {
            CatalogLeafCategoryRules::assertValidLeafParent($legacyPowerBanks->id);
            $this->fail('Inactive legacy power banks leaf must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subcategory_id', $exception->errors());
        }

        CatalogLeafCategoryRules::assertValidLeafParent(
            Category::query()->where('slug', self::CANONICAL_CHARGERS)->value('id'),
        );
        CatalogLeafCategoryRules::assertValidLeafParent(
            Category::query()->where('slug', self::CANONICAL_POWER_BANKS)->value('id'),
        );
    }

    public function test_phone_accessories_discovery_anchors_remain(): void
    {
        $this->assertNotNull(Category::query()->where('slug', 'phones-tablets-phone-accessories')->first());
        $this->assertNotNull(Category::query()->where('slug', self::CANONICAL_CHARGERS)->first());
        $this->assertNotNull(Category::query()->where('slug', self::CANONICAL_POWER_BANKS)->first());
        $this->assertNotNull(Category::query()->where('slug', 'phones-tablets-phone-accessories-phone-cases')->first());
        $this->assertNotNull(Category::query()->where('slug', 'phones-tablets-phone-accessories-cables')->first());
    }

    public function test_category_seeder_does_not_recreate_legacy_flat_duplicates(): void
    {
        $this->assertNull(Category::query()->where('slug', self::LEGACY_FLAT_CHARGERS)->first());
        $this->assertNull(Category::query()->where('slug', self::LEGACY_FLAT_POWER_BANKS)->first());

        $before = Category::query()->count();
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);

        $this->assertSame($before, Category::query()->count());
        $this->assertNull(Category::query()->where('slug', self::LEGACY_FLAT_CHARGERS)->first());
        $this->assertNull(Category::query()->where('slug', self::LEGACY_FLAT_POWER_BANKS)->first());
    }

    public function test_consumer_electronics_never_receives_power_banks_from_seeders(): void
    {
        $consumerNames = CategorySeeder::departmentCategories()['consumer-electronics'] ?? [];
        $this->assertNotContains('Power Banks', $consumerNames);
        $this->assertNotContains('Phone Accessories', $consumerNames);
        $this->assertNotContains('Mobile Accessories', $consumerNames);

        $consumerId = Department::query()
            ->where('slug', MobileAccessoriesTaxonomy::CONSUMER_ELECTRONICS_DEPARTMENT_SLUG)
            ->value('id');

        $this->assertNotNull($consumerId);
        $this->assertNull(
            Category::query()
                ->where('department_id', $consumerId)
                ->where('name', 'Power Banks')
                ->first(),
        );
        foreach (MobileAccessoriesTaxonomy::FORBIDDEN_POWER_BANK_SLUGS as $slug) {
            $this->assertNull(Category::query()->where('slug', $slug)->first(), $slug);
        }

        $before = Category::query()
            ->where('department_id', $consumerId)
            ->where('name', 'Power Banks')
            ->count();

        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);
        $this->seed(CatalogProductTypeSeeder::class);

        $this->assertSame(
            $before,
            Category::query()
                ->where('department_id', $consumerId)
                ->where('name', 'Power Banks')
                ->count(),
        );
        $this->assertNotNull(
            Category::query()->where('slug', MobileAccessoriesTaxonomy::CANONICAL_POWER_BANKS_SLUG)->first(),
        );
        $this->assertTrue(MobileAccessoriesTaxonomy::isForbiddenDepartmentCategory(
            'consumer-electronics',
            'Power Banks',
        ));
    }

    public function test_phone_accessories_leaves_cover_core_mobile_families(): void
    {
        $accessories = Category::query()
            ->where('slug', MobileAccessoriesTaxonomy::CANONICAL_ACCESSORIES_SLUG)
            ->firstOrFail();

        $childNames = Category::query()
            ->where('parent_id', $accessories->id)
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing(
            ['Phone Cases', 'Chargers', 'Power Banks', 'Screen Protectors', 'Cables'],
            $childNames,
        );

        $this->assertNotNull(
            CatalogProductType::query()
                ->where('slug', MobileAccessoriesTaxonomy::CANONICAL_CHARGERS_SLUG.'-wireless-charger')
                ->first(),
        );
    }
}
