<?php

namespace Tests\Feature\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Store;
use App\Services\Catalog\CatalogFoundationSnapshotExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CatalogFoundationSnapshotExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_snapshot_file_with_envelope_and_counts(): void
    {
        $fixture = $this->seedChinaAndTzFixture();
        $outputPath = storage_path('app/catalog-snapshots/test-export-'.uniqid('', true).'.json');

        $this->artisan('catalog:snapshot-export', [
            '--path' => $outputPath,
            '--scope' => 'china-admin',
        ])->assertSuccessful();

        $this->assertFileExists($outputPath);

        $payload = json_decode((string) file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('catalog-foundation-snapshot', $payload['format']);
        $this->assertSame(1, $payload['version']);
        $this->assertSame('china-admin', $payload['scope']);
        $this->assertArrayHasKey('generated_at', $payload);
        $this->assertArrayHasKey('app_env', $payload['source']);
        $this->assertArrayHasKey('app_url', $payload['source']);
        $this->assertArrayHasKey('counts', $payload);
        $this->assertArrayHasKey('tables', $payload);

        foreach ([
            'departments',
            'categories',
            'catalog_product_types',
            'catalog_attributes',
            'catalog_attribute_options',
            'catalog_product_type_attributes',
        ] as $table) {
            $this->assertArrayHasKey($table, $payload['tables']);
            $this->assertSame(
                count($payload['tables'][$table]),
                $payload['counts'][$table],
            );
        }

        $departmentIds = collect($payload['tables']['departments'])->pluck('id')->all();
        $this->assertContains($fixture['chinaDepartment']->id, $departmentIds);
        $this->assertSame(
            $fixture['chinaDepartment']->id,
            collect($payload['tables']['departments'])->firstWhere('id', $fixture['chinaDepartment']->id)['id'],
        );

        File::delete($outputPath);
    }

    public function test_snapshot_preserves_uuids_and_includes_soft_deleted_rows(): void
    {
        $fixture = $this->seedChinaAndTzFixture();

        $snapshot = app(CatalogFoundationSnapshotExporter::class)->build();

        $categoryIds = collect($snapshot['tables']['categories'])->pluck('id')->all();
        $this->assertContains($fixture['chinaCategory']->id, $categoryIds);
        $this->assertContains($fixture['softDeletedCategory']->id, $categoryIds);

        $softDeleted = collect($snapshot['tables']['categories'])
            ->firstWhere('id', $fixture['softDeletedCategory']->id);

        $this->assertNotNull($softDeleted['deleted_at']);
        $this->assertSame($fixture['softDeletedCategory']->slug, $softDeleted['slug']);

        $typeIds = collect($snapshot['tables']['catalog_product_types'])->pluck('id')->all();
        $this->assertContains($fixture['chinaProductType']->id, $typeIds);
        $this->assertContains($fixture['softDeletedProductType']->id, $typeIds);

        $softDeletedType = collect($snapshot['tables']['catalog_product_types'])
            ->firstWhere('id', $fixture['softDeletedProductType']->id);
        $this->assertNotNull($softDeletedType['deleted_at']);

        $attributeIds = collect($snapshot['tables']['catalog_attributes'])->pluck('id')->all();
        $this->assertContains($fixture['attribute']->id, $attributeIds);

        $optionIds = collect($snapshot['tables']['catalog_attribute_options'])->pluck('id')->all();
        $this->assertContains($fixture['option']->id, $optionIds);

        $mappingIds = collect($snapshot['tables']['catalog_product_type_attributes'])->pluck('id')->all();
        $this->assertContains($fixture['mappingId'], $mappingIds);
    }

    public function test_china_admin_scope_excludes_tz_store_catalog(): void
    {
        $fixture = $this->seedChinaAndTzFixture();

        $snapshot = app(CatalogFoundationSnapshotExporter::class)->build('china-admin');

        $categoryIds = collect($snapshot['tables']['categories'])->pluck('id')->all();
        $this->assertNotContains($fixture['tzCategory']->id, $categoryIds);
        $this->assertNotContains($fixture['tzChildCategory']->id, $categoryIds);

        $typeIds = collect($snapshot['tables']['catalog_product_types'])->pluck('id')->all();
        $this->assertNotContains($fixture['tzProductType']->id, $typeIds);

        $departmentIds = collect($snapshot['tables']['departments'])->pluck('id')->all();
        $this->assertNotContains($fixture['tzOnlyDepartment']->id, $departmentIds);

        foreach ($snapshot['tables']['categories'] as $category) {
            $this->assertSame('china', $category['origin']);
            $this->assertNull($category['store_id']);
        }
    }

    public function test_unsupported_scope_fails(): void
    {
        $this->artisan('catalog:snapshot-export', [
            '--scope' => 'all-foundation',
            '--path' => storage_path('app/catalog-snapshots/should-not-exist.json'),
        ])->assertFailed();
    }

    /**
     * @return array{
     *     chinaDepartment: Department,
     *     chinaCategory: Category,
     *     softDeletedCategory: Category,
     *     chinaProductType: CatalogProductType,
     *     softDeletedProductType: CatalogProductType,
     *     attribute: CatalogAttribute,
     *     option: CatalogAttributeOption,
     *     mappingId: string,
     *     tzOnlyDepartment: Department,
     *     tzCategory: Category,
     *     tzChildCategory: Category,
     *     tzProductType: CatalogProductType
     * }
     */
    private function seedChinaAndTzFixture(): array
    {
        $chinaDepartment = Department::factory()->create([
            'name' => 'Snapshot China Dept',
            'slug' => 'snapshot-china-dept',
        ]);

        $chinaCategory = Category::factory()->forDepartment($chinaDepartment)->china()->create([
            'name' => 'China Root',
            'slug' => 'snapshot-china-root',
            'parent_id' => null,
            'store_id' => null,
            'is_active' => true,
        ]);

        $softDeletedCategory = Category::factory()->forDepartment($chinaDepartment)->china()->create([
            'name' => 'China Soft Deleted',
            'slug' => 'snapshot-china-soft-deleted',
            'parent_id' => $chinaCategory->id,
            'store_id' => null,
            'is_active' => true,
        ]);

        $chinaProductType = CatalogProductType::factory()->create([
            'subcategory_id' => $chinaCategory->id,
            'name' => 'China Type',
            'slug' => 'snapshot-china-type',
        ]);

        $softDeletedProductType = CatalogProductType::factory()->create([
            'subcategory_id' => $softDeletedCategory->id,
            'name' => 'China Soft Type',
            'slug' => 'snapshot-china-soft-type',
        ]);

        $attribute = CatalogAttribute::factory()->create([
            'name' => 'Snapshot Attr',
            'slug' => 'snapshot-attr',
        ]);

        $option = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $attribute->id,
            'value' => 'Option A',
            'slug' => 'option-a',
        ]);

        $chinaProductType->attributes()->sync([
            $attribute->id => ['is_required' => false, 'sort_order' => 1],
        ]);

        $mappingId = $chinaProductType->attributes()->first()?->pivot?->id;
        $this->assertNotNull($mappingId);

        $softDeletedProductType->delete();
        $softDeletedCategory->delete();

        $store = Store::query()->create([
            'code' => 'SNAPTZ',
            'name' => 'Snapshot TZ Store',
            'slug' => 'snapshot-tz-store',
            'is_active' => true,
        ]);

        $tzOnlyDepartment = Department::factory()->create([
            'name' => 'TZ Only Dept',
            'slug' => 'snapshot-tz-only-dept',
        ]);

        $tzCategory = Category::factory()->forDepartment($tzOnlyDepartment)->tz()->create([
            'name' => 'TZ Root',
            'slug' => 'snapshot-tz-root',
            'parent_id' => null,
            'store_id' => $store->id,
        ]);

        $tzChildCategory = Category::factory()->forDepartment($tzOnlyDepartment)->tz()->create([
            'name' => 'TZ Child',
            'slug' => 'snapshot-tz-child',
            'parent_id' => $tzCategory->id,
            'store_id' => $store->id,
        ]);

        $tzProductType = CatalogProductType::factory()->create([
            'subcategory_id' => $tzChildCategory->id,
            'name' => 'TZ Type',
            'slug' => 'snapshot-tz-type',
        ]);

        $tzAttribute = CatalogAttribute::factory()->create([
            'name' => 'TZ Attr',
            'slug' => 'snapshot-tz-attr',
        ]);
        $tzProductType->attributes()->sync([
            $tzAttribute->id => ['is_required' => true, 'sort_order' => 1],
        ]);

        return [
            'chinaDepartment' => $chinaDepartment,
            'chinaCategory' => $chinaCategory,
            'softDeletedCategory' => $softDeletedCategory,
            'chinaProductType' => $chinaProductType,
            'softDeletedProductType' => $softDeletedProductType,
            'attribute' => $attribute,
            'option' => $option,
            'mappingId' => (string) $mappingId,
            'tzOnlyDepartment' => $tzOnlyDepartment,
            'tzCategory' => $tzCategory,
            'tzChildCategory' => $tzChildCategory,
            'tzProductType' => $tzProductType,
        ];
    }
}
