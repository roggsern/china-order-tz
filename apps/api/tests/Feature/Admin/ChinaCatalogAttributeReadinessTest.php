<?php

namespace Tests\Feature\Admin;

use App\Enums\CatalogOrigin;
use App\Models\CatalogAttribute;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use Database\Seeders\CatalogAttributeSeeder;
use Database\Seeders\CatalogProductTypeSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CoreDatabaseSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\SubcategorySeeder;
use Database\Support\CatalogAttributeDomainMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChinaCatalogAttributeReadinessTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_DEPARTMENTS = [
        'home-appliances',
        'home-furniture',
        'beauty-personal-care',
        'health-medical',
        'jewelry-watches',
        'sports-outdoors',
        'automotive',
        'industrial-tools',
        'toys-kids',
        'pet-supplies',
        'groceries',
    ];

    private const MEDICAL_SIMPLE = [
        'Syringe',
        'Cotton Wool',
        'Alcohol Swab',
        'Medical Tape',
    ];

    public function test_all_china_departments_have_product_types_and_eligible_categories_covered(): void
    {
        $this->seedChinaTypesAndAttributes();

        foreach (DepartmentSeeder::definitions() as $definition) {
            $slug = Str::slug($definition['name']);
            $department = Department::query()->where('slug', $slug)->firstOrFail();

            $typeCount = CatalogProductType::query()
                ->whereHas(
                    'subcategory',
                    fn ($query) => $query
                        ->where('department_id', $department->id)
                        ->where('origin', CatalogOrigin::China),
                )
                ->count();

            $this->assertGreaterThan(0, $typeCount, "Department [{$slug}] must have Catalog Product Types.");
        }

        $eligible = Category::query()
            ->where('origin', CatalogOrigin::China)
            ->whereNotNull('department_id')
            ->whereNull('deleted_at')
            ->whereDoesntHave('children')
            ->get()
            ->filter(function (Category $category): bool {
                if ($category->parent_id !== null) {
                    return true;
                }

                return ! Category::query()
                    ->where('department_id', $category->department_id)
                    ->where('name', $category->name)
                    ->whereNotNull('parent_id')
                    ->exists();
            });

        foreach ($eligible as $category) {
            $this->assertGreaterThan(
                0,
                CatalogProductType::query()->where('subcategory_id', $category->id)->count(),
                "Eligible leaf [{$category->slug}] must have a Catalog Product Type.",
            );
        }
    }

    public function test_new_product_types_have_specification_readiness_without_forcing_variants(): void
    {
        $this->seedChinaTypesAndAttributes();

        $types = $this->newDepartmentTypes();
        $this->assertGreaterThanOrEqual(430, $types->count());

        $withSpecs = 0;
        $withVariants = 0;
        $simpleFriendly = 0;

        foreach ($types as $type) {
            $slugs = $type->attributes->pluck('slug')->all();
            $this->assertNotEmpty($slugs, "Type [{$type->name}] must have specification attributes.");
            $withSpecs++;

            if (count(array_intersect($slugs, CatalogAttributeDomainMap::VARIANT_ATTRIBUTE_SLUGS)) > 0) {
                $withVariants++;
            } else {
                $simpleFriendly++;
            }
        }

        $this->assertSame($types->count(), $withSpecs);
        $this->assertGreaterThan(0, $withVariants);
        $this->assertGreaterThan(0, $simpleFriendly);
        $this->assertLessThan($types->count(), $withVariants);
    }

    public function test_groceries_do_not_receive_color_or_apparel_size(): void
    {
        $this->seedChinaTypesAndAttributes();

        foreach ($this->typesInDepartment('groceries') as $type) {
            $slugs = $type->attributes->pluck('slug')->all();
            $this->assertNotContains('color', $slugs, $type->name);
            $this->assertNotContains('size', $slugs, $type->name);
            $this->assertContains('brand', $slugs, $type->name);
        }
    }

    public function test_medical_supplies_do_not_receive_apparel_size(): void
    {
        $this->seedChinaTypesAndAttributes();

        foreach ($this->typesInDepartment('health-medical') as $type) {
            $slugs = $type->attributes->pluck('slug')->all();
            $this->assertNotContains('size', $slugs, $type->name);

            if (in_array($type->name, self::MEDICAL_SIMPLE, true)) {
                $this->assertEmpty(
                    array_intersect($slugs, CatalogAttributeDomainMap::VARIANT_ATTRIBUTE_SLUGS),
                    "Simple medical type [{$type->name}] must not receive forced variant axes.",
                );
            }
        }
    }

    public function test_industrial_tools_do_not_receive_apparel_size(): void
    {
        $this->seedChinaTypesAndAttributes();

        foreach ($this->typesInDepartment('industrial-tools') as $type) {
            $this->assertNotContains('size', $type->attributes->pluck('slug')->all(), $type->name);
        }
    }

    public function test_sports_footwear_uses_shoe_size_not_apparel_size(): void
    {
        $this->seedChinaTypesAndAttributes();

        foreach (['Running Shoes', 'Training Shoes', 'Football Boots', 'Hiking Boots'] as $name) {
            $type = $this->typeByNameInDepartment('sports-outdoors', $name);
            $slugs = $type->attributes->pluck('slug')->all();
            $this->assertContains('shoe-size', $slugs);
            $this->assertNotContains('size', $slugs);
        }
    }

    public function test_sportswear_may_receive_apparel_size(): void
    {
        $this->seedChinaTypesAndAttributes();

        $type = $this->typeByNameInDepartment('sports-outdoors', 'Sports T-Shirt');
        $slugs = $type->attributes->pluck('slug')->all();
        $this->assertContains('size', $slugs);
        $this->assertContains('color', $slugs);
        $this->assertNotContains('shoe-size', $slugs);
    }

    public function test_jewelry_rings_receive_ring_size(): void
    {
        $this->seedChinaTypesAndAttributes();

        foreach (['Gold Ring', 'Silver Ring', 'Fashion Ring', 'Engagement Ring'] as $name) {
            $type = $this->typeByNameInDepartment('jewelry-watches', $name);
            $this->assertContains('ring-size', $type->attributes->pluck('slug')->all());
        }
    }

    public function test_appliances_receive_voltage_and_capacity(): void
    {
        $this->seedChinaTypesAndAttributes();

        $type = $this->typeByNameInDepartment('home-appliances', 'Refrigerator');
        $slugs = $type->attributes->pluck('slug')->all();
        $this->assertContains('voltage', $slugs);
        $this->assertContains('capacity', $slugs);
        $this->assertContains('brand', $slugs);
    }

    public function test_automotive_fitment_maps_and_phone_holder_is_not_phones_domain(): void
    {
        $this->seedChinaTypesAndAttributes();

        $holder = $this->typeByNameInDepartment('automotive', 'Phone Holder');
        $slugs = $holder->attributes->pluck('slug')->all();

        $this->assertContains('fitment', $slugs);
        $this->assertContains('vehicle-make', $slugs);
        $this->assertNotContains('ram', $slugs);
        $this->assertNotContains('storage', $slugs);
        $this->assertNotContains('battery-capacity', $slugs);
    }

    public function test_beauty_shade_volume_and_wig_mappings(): void
    {
        $this->seedChinaTypesAndAttributes();

        $lipstick = $this->typeByNameInDepartment('beauty-personal-care', 'Lipstick');
        $this->assertContains('shade', $lipstick->attributes->pluck('slug')->all());
        $this->assertContains('volume', $lipstick->attributes->pluck('slug')->all());

        $wig = $this->typeByNameInDepartment('beauty-personal-care', 'Lace Front Wig');
        $wigSlugs = $wig->attributes->pluck('slug')->all();
        $this->assertContains('wig-length', $wigSlugs);
        $this->assertContains('wig-texture', $wigSlugs);
    }

    public function test_false_substring_matches_are_impossible(): void
    {
        $this->seedChinaTypesAndAttributes();

        $dressingTable = $this->typeByNameInDepartment('home-furniture', 'Dressing Table');
        $dressingSlugs = $dressingTable->attributes->pluck('slug')->all();
        $this->assertNotContains('size', $dressingSlugs);
        $this->assertNotContains('fabric', $dressingSlugs);
        $this->assertNotContains('gender', $dressingSlugs);
        $this->assertContains('build-material', $dressingSlugs);

        $headphone = $this->typeByNameInDepartment('professional-audio', 'Studio Headphone');
        $headSlugs = $headphone->attributes->pluck('slug')->all();
        $this->assertNotContains('ram', $headSlugs);
        $this->assertNotContains('storage', $headSlugs);
        $this->assertContains('impedance', $headSlugs);

        $micMount = $this->typeByNameInDepartment('professional-audio', 'Microphone Shock Mount');
        $micSlugs = $micMount->attributes->pluck('slug')->all();
        $this->assertNotContains('ram', $micSlugs);
        $this->assertNotContains('storage', $micSlugs);
    }

    public function test_tz_local_mappings_remain_unchanged_by_china_attribute_sync(): void
    {
        $this->seed(CoreDatabaseSeeder::class);

        $tzBefore = CatalogProductType::query()
            ->whereHas('subcategory', fn ($query) => $query->where('origin', CatalogOrigin::Tz))
            ->with('attributes')
            ->get()
            ->mapWithKeys(fn (CatalogProductType $type) => [
                $type->id => $type->attributes->pluck('id')->sort()->values()->all(),
            ])
            ->all();

        $this->assertNotEmpty($tzBefore);

        $this->seed(CatalogAttributeSeeder::class);

        foreach ($tzBefore as $typeId => $attributeIds) {
            $fresh = CatalogProductType::query()->with('attributes')->findOrFail($typeId);
            $this->assertSame(
                $attributeIds,
                $fresh->attributes->pluck('id')->sort()->values()->all(),
            );
        }
    }

    public function test_narrow_seeders_are_idempotent_and_preserve_product_references(): void
    {
        $this->seedChinaTypesAndAttributes();

        $type = $this->typeByNameInDepartment('home-appliances', 'Refrigerator');
        $product = Product::factory()->create([
            'catalog_product_type_id' => $type->id,
            'category_id' => $type->subcategory_id,
        ]);

        $typeIds = CatalogProductType::query()->orderBy('id')->pluck('id')->all();
        $attributeIds = CatalogAttribute::query()->orderBy('id')->pluck('id')->all();
        $typeCount = count($typeIds);
        $attributeCount = count($attributeIds);

        $this->seed(CatalogProductTypeSeeder::class);
        $this->seed(CatalogAttributeSeeder::class);
        $this->seed(CatalogProductTypeSeeder::class);
        $this->seed(CatalogAttributeSeeder::class);

        $this->assertSame($typeCount, CatalogProductType::query()->count());
        $this->assertSame($attributeCount, CatalogAttribute::query()->count());
        $this->assertSame($typeIds, CatalogProductType::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame($attributeIds, CatalogAttribute::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame($type->id, $product->fresh()->catalog_product_type_id);
        $this->assertSame($type->subcategory_id, $product->fresh()->category_id);
    }

    public function test_wizard_api_returns_usable_attributes_after_narrow_seeders(): void
    {
        $this->seedChinaTypesAndAttributes();

        $type = $this->typeByNameInDepartment('jewelry-watches', 'Gold Ring');
        $this->assertTrue($type->attributes->contains(fn ($attribute) => $attribute->slug === 'ring-size'));
        $this->assertTrue(
            $type->attributes->firstWhere('slug', 'ring-size')?->options->isNotEmpty() ?? false,
        );

        $refrigerator = $this->typeByNameInDepartment('home-appliances', 'Refrigerator');
        $this->assertTrue($refrigerator->attributes->contains(fn ($attribute) => $attribute->slug === 'voltage'));
    }

    public function test_group_type_matchers_are_disabled(): void
    {
        $this->assertSame([], CatalogAttributeSeeder::groupTypeMatchers());
    }

    public function test_domain_map_rejects_cross_journey_types(): void
    {
        $this->seed(CoreDatabaseSeeder::class);

        $tzType = CatalogProductType::query()
            ->whereHas('subcategory', fn ($query) => $query->where('origin', CatalogOrigin::Tz))
            ->firstOrFail();

        $this->assertSame([], CatalogAttributeDomainMap::attributeSlugsFor($tzType));
    }

    private function seedChinaTypesAndAttributes(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);
        $this->seed(CatalogProductTypeSeeder::class);
        $this->seed(CatalogAttributeSeeder::class);
    }

    /**
     * @return \Illuminate\Support\Collection<int, CatalogProductType>
     */
    private function newDepartmentTypes()
    {
        return CatalogProductType::query()
            ->with('attributes')
            ->whereHas(
                'subcategory.department',
                fn ($query) => $query->whereIn('slug', self::NEW_DEPARTMENTS),
            )
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, CatalogProductType>
     */
    private function typesInDepartment(string $departmentSlug)
    {
        return CatalogProductType::query()
            ->with('attributes')
            ->whereHas(
                'subcategory.department',
                fn ($query) => $query->where('slug', $departmentSlug),
            )
            ->get();
    }

    private function typeByNameInDepartment(string $departmentSlug, string $name): CatalogProductType
    {
        return CatalogProductType::query()
            ->with(['attributes.options'])
            ->where('name', $name)
            ->whereHas(
                'subcategory.department',
                fn ($query) => $query->where('slug', $departmentSlug),
            )
            ->firstOrFail();
    }
}
