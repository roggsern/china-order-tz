<?php

namespace Tests\Feature\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductType;
use App\Models\CatalogProductTypeAttribute;
use App\Models\Category;
use App\Models\Department;
use App\Services\Catalog\CatalogFoundationSnapshotExporter;
use App\Services\Catalog\CatalogFoundationSnapshotImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogFoundationSnapshotImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_creates_no_database_changes(): void
    {
        $snapshotPath = $this->writeSnapshot($this->buildSnapshotPayload());

        $departmentCount = Department::withTrashed()->count();
        $categoryCount = Category::withTrashed()->count();

        $this->artisan('catalog:snapshot-import', [
            'path' => $snapshotPath,
        ])->assertSuccessful();

        $this->assertSame($departmentCount, Department::withTrashed()->count());
        $this->assertSame($categoryCount, Category::withTrashed()->count());
        $this->assertNull(Department::withTrashed()->find('019f0000-0000-7000-8000-000000000001'));

        File::delete($snapshotPath);
    }

    public function test_execute_upserts_by_uuid_and_preserves_ids(): void
    {
        $departmentId = (string) Str::uuid();
        $rootId = (string) Str::uuid();
        $childId = (string) Str::uuid();
        $typeId = (string) Str::uuid();
        $attributeId = (string) Str::uuid();
        $optionId = (string) Str::uuid();
        $mappingId = (string) Str::uuid();

        $snapshotPath = $this->writeSnapshot($this->buildSnapshotPayload([
            'department_id' => $departmentId,
            'root_id' => $rootId,
            'child_id' => $childId,
            'type_id' => $typeId,
            'attribute_id' => $attributeId,
            'option_id' => $optionId,
            'mapping_id' => $mappingId,
        ]));

        $this->artisan('catalog:snapshot-import', [
            'path' => $snapshotPath,
            '--execute' => true,
            '--allow-env' => 'testing',
        ])->assertSuccessful();

        $department = Department::query()->findOrFail($departmentId);
        $this->assertSame('Import Home Care', $department->name);
        $this->assertSame('import-home-care', $department->slug);

        $root = Category::query()->findOrFail($rootId);
        $child = Category::query()->findOrFail($childId);
        $this->assertNull($root->parent_id);
        $this->assertSame($rootId, $child->parent_id);
        $this->assertSame($departmentId, $child->department_id);

        $type = CatalogProductType::query()->findOrFail($typeId);
        $this->assertSame($childId, $type->subcategory_id);

        $this->assertNotNull(CatalogAttribute::query()->find($attributeId));
        $this->assertNotNull(CatalogAttributeOption::query()->find($optionId));
        $this->assertNotNull(CatalogProductTypeAttribute::query()->find($mappingId));

        File::delete($snapshotPath);
    }

    public function test_soft_delete_and_restore_behavior(): void
    {
        $departmentId = (string) Str::uuid();
        $rootId = (string) Str::uuid();
        $childId = (string) Str::uuid();
        $typeId = (string) Str::uuid();
        $attributeId = (string) Str::uuid();
        $optionId = (string) Str::uuid();
        $mappingId = (string) Str::uuid();

        $ids = [
            'department_id' => $departmentId,
            'root_id' => $rootId,
            'child_id' => $childId,
            'type_id' => $typeId,
            'attribute_id' => $attributeId,
            'option_id' => $optionId,
            'mapping_id' => $mappingId,
        ];

        $activeSnapshot = $this->writeSnapshot($this->buildSnapshotPayload($ids));
        $this->artisan('catalog:snapshot-import', [
            'path' => $activeSnapshot,
            '--execute' => true,
            '--allow-env' => 'testing',
        ])->assertSuccessful();
        File::delete($activeSnapshot);

        $deletedPayload = $this->buildSnapshotPayload($ids);
        $deletedPayload['tables']['departments'][0]['deleted_at'] = '2026-08-08T10:00:00+00:00';
        $deletedPayload['tables']['categories'][0]['deleted_at'] = '2026-08-08T10:00:01+00:00';
        $deletedPath = $this->writeSnapshot($deletedPayload);

        $this->artisan('catalog:snapshot-import', [
            'path' => $deletedPath,
            '--execute' => true,
            '--allow-env' => 'testing',
        ])->assertSuccessful();

        $this->assertNotNull(Department::withTrashed()->findOrFail($departmentId)->deleted_at);
        $this->assertNotNull(Category::withTrashed()->findOrFail($rootId)->deleted_at);
        File::delete($deletedPath);

        $restorePayload = $this->buildSnapshotPayload($ids);
        $restorePath = $this->writeSnapshot($restorePayload);
        $this->artisan('catalog:snapshot-import', [
            'path' => $restorePath,
            '--execute' => true,
            '--allow-env' => 'testing',
        ])->assertSuccessful();

        $this->assertNull(Department::query()->findOrFail($departmentId)->deleted_at);
        $this->assertNull(Category::query()->findOrFail($rootId)->deleted_at);

        File::delete($restorePath);
    }

    public function test_parent_categories_are_imported_before_children(): void
    {
        $departmentId = (string) Str::uuid();
        $rootId = (string) Str::uuid();
        $childId = (string) Str::uuid();
        $typeId = (string) Str::uuid();
        $attributeId = (string) Str::uuid();
        $optionId = (string) Str::uuid();
        $mappingId = (string) Str::uuid();

        $payload = $this->buildSnapshotPayload([
            'department_id' => $departmentId,
            'root_id' => $rootId,
            'child_id' => $childId,
            'type_id' => $typeId,
            'attribute_id' => $attributeId,
            'option_id' => $optionId,
            'mapping_id' => $mappingId,
        ]);

        // Intentionally reverse category order in the file.
        $payload['tables']['categories'] = array_reverse($payload['tables']['categories']);

        $path = $this->writeSnapshot($payload);

        $this->artisan('catalog:snapshot-import', [
            'path' => $path,
            '--execute' => true,
            '--allow-env' => 'testing',
        ])->assertSuccessful();

        $child = Category::query()->findOrFail($childId);
        $this->assertSame($rootId, $child->parent_id);
        $this->assertNotNull(Category::query()->find($rootId));

        File::delete($path);
    }

    public function test_production_environment_blocks_execute_without_force_env(): void
    {
        $path = $this->writeSnapshot($this->buildSnapshotPayload());

        config(['app.env' => 'production']);

        $this->artisan('catalog:snapshot-import', [
            'path' => $path,
            '--execute' => true,
            '--allow-env' => 'production',
        ])->assertFailed();

        $this->assertNull(Department::withTrashed()->find('019f0000-0000-7000-8000-000000000001'));

        File::delete($path);
    }

    public function test_replace_foundation_removes_local_only_mappings(): void
    {
        $departmentId = (string) Str::uuid();
        $rootId = (string) Str::uuid();
        $childId = (string) Str::uuid();
        $typeId = (string) Str::uuid();
        $attributeId = (string) Str::uuid();
        $optionId = (string) Str::uuid();
        $mappingId = (string) Str::uuid();

        $path = $this->writeSnapshot($this->buildSnapshotPayload([
            'department_id' => $departmentId,
            'root_id' => $rootId,
            'child_id' => $childId,
            'type_id' => $typeId,
            'attribute_id' => $attributeId,
            'option_id' => $optionId,
            'mapping_id' => $mappingId,
        ]));

        $this->artisan('catalog:snapshot-import', [
            'path' => $path,
            '--execute' => true,
            '--allow-env' => 'testing',
        ])->assertSuccessful();

        $extraAttribute = CatalogAttribute::factory()->create([
            'slug' => 'local-only-attr',
        ]);

        $type = CatalogProductType::query()->findOrFail($typeId);
        $type->attributes()->syncWithoutDetaching([
            $extraAttribute->id => ['is_required' => false, 'sort_order' => 99],
        ]);

        $extraMappingId = CatalogProductTypeAttribute::query()
            ->where('catalog_product_type_id', $typeId)
            ->where('catalog_attribute_id', $extraAttribute->id)
            ->value('id');
        $this->assertNotNull($extraMappingId);

        $this->artisan('catalog:snapshot-import', [
            'path' => $path,
            '--execute' => true,
            '--mode' => 'replace-foundation',
            '--allow-env' => 'testing',
        ])->assertSuccessful();

        $this->assertNull(CatalogProductTypeAttribute::query()->find($extraMappingId));
        $this->assertNotNull(CatalogProductTypeAttribute::query()->find($mappingId));

        File::delete($path);
    }

    public function test_dry_run_reports_planned_creates(): void
    {
        $path = $this->writeSnapshot($this->buildSnapshotPayload());

        $result = app(CatalogFoundationSnapshotImporter::class)->import(
            path: $path,
            execute: false,
            allowEnvs: ['testing'],
        );

        $this->assertTrue($result['dry_run']);
        $this->assertGreaterThan(0, $result['counts']['departments']['create']);
        $this->assertGreaterThan(0, $result['counts']['categories']['create']);
        $this->assertSame(0, Department::withTrashed()->where('slug', 'import-home-care')->count());

        File::delete($path);
    }

    /**
     * @param  array{
     *     department_id?: string,
     *     root_id?: string,
     *     child_id?: string,
     *     type_id?: string,
     *     attribute_id?: string,
     *     option_id?: string,
     *     mapping_id?: string
     * }  $ids
     * @return array<string, mixed>
     */
    private function buildSnapshotPayload(array $ids = []): array
    {
        $departmentId = $ids['department_id'] ?? '019f0000-0000-7000-8000-000000000001';
        $rootId = $ids['root_id'] ?? '019f0000-0000-7000-8000-000000000002';
        $childId = $ids['child_id'] ?? '019f0000-0000-7000-8000-000000000003';
        $typeId = $ids['type_id'] ?? '019f0000-0000-7000-8000-000000000004';
        $attributeId = $ids['attribute_id'] ?? '019f0000-0000-7000-8000-000000000005';
        $optionId = $ids['option_id'] ?? '019f0000-0000-7000-8000-000000000006';
        $mappingId = $ids['mapping_id'] ?? '019f0000-0000-7000-8000-000000000007';

        return [
            'format' => CatalogFoundationSnapshotExporter::FORMAT,
            'version' => CatalogFoundationSnapshotExporter::VERSION,
            'generated_at' => now()->toIso8601String(),
            'scope' => 'china-admin',
            'source' => [
                'app_env' => 'production',
                'app_url' => 'https://api.example.test',
            ],
            'counts' => [
                'departments' => 1,
                'categories' => 2,
                'catalog_product_types' => 1,
                'catalog_attributes' => 1,
                'catalog_attribute_options' => 1,
                'catalog_product_type_attributes' => 1,
            ],
            'tables' => [
                'departments' => [[
                    'id' => $departmentId,
                    'name' => 'Import Home Care',
                    'slug' => 'import-home-care',
                    'icon' => null,
                    'image' => null,
                    'description' => null,
                    'sort_order' => 18,
                    'is_active' => true,
                    'created_at' => '2026-08-08T06:00:00+00:00',
                    'updated_at' => '2026-08-08T06:00:00+00:00',
                    'deleted_at' => null,
                ]],
                'categories' => [
                    [
                        'id' => $rootId,
                        'department_id' => $departmentId,
                        'parent_id' => null,
                        'store_id' => null,
                        'origin' => CatalogOrigin::China->value,
                        'product_type_id' => null,
                        'name' => 'Pest Control',
                        'slug' => 'import-pest-control',
                        'description' => null,
                        'image' => null,
                        'sort_order' => 10,
                        'is_active' => true,
                        'created_at' => '2026-08-08T06:01:00+00:00',
                        'updated_at' => '2026-08-08T06:01:00+00:00',
                        'deleted_at' => null,
                    ],
                    [
                        'id' => $childId,
                        'department_id' => $departmentId,
                        'parent_id' => $rootId,
                        'store_id' => null,
                        'origin' => CatalogOrigin::China->value,
                        'product_type_id' => null,
                        'name' => 'Sprays',
                        'slug' => 'import-pest-control-sprays',
                        'description' => null,
                        'image' => null,
                        'sort_order' => 20,
                        'is_active' => true,
                        'created_at' => '2026-08-08T06:02:00+00:00',
                        'updated_at' => '2026-08-08T06:02:00+00:00',
                        'deleted_at' => null,
                    ],
                ],
                'catalog_attributes' => [[
                    'id' => $attributeId,
                    'name' => 'Volume',
                    'slug' => 'import-volume',
                    'type' => 'select',
                    'unit' => 'ml',
                    'is_filterable' => true,
                    'is_required' => false,
                    'sort_order' => 1,
                    'is_active' => true,
                    'created_at' => '2026-08-08T06:03:00+00:00',
                    'updated_at' => '2026-08-08T06:03:00+00:00',
                    'deleted_at' => null,
                ]],
                'catalog_attribute_options' => [[
                    'id' => $optionId,
                    'catalog_attribute_id' => $attributeId,
                    'value' => '500ml',
                    'slug' => '500ml',
                    'sort_order' => 1,
                    'created_at' => '2026-08-08T06:03:10+00:00',
                    'updated_at' => '2026-08-08T06:03:10+00:00',
                ]],
                'catalog_product_types' => [[
                    'id' => $typeId,
                    'subcategory_id' => $childId,
                    'name' => 'Insecticide Spray',
                    'slug' => 'import-insecticide-spray',
                    'image' => null,
                    'description' => null,
                    'sort_order' => 1,
                    'is_active' => true,
                    'created_at' => '2026-08-08T06:04:00+00:00',
                    'updated_at' => '2026-08-08T06:04:00+00:00',
                    'deleted_at' => null,
                ]],
                'catalog_product_type_attributes' => [[
                    'id' => $mappingId,
                    'catalog_product_type_id' => $typeId,
                    'catalog_attribute_id' => $attributeId,
                    'is_required' => false,
                    'sort_order' => 1,
                    'created_at' => '2026-08-08T06:05:00+00:00',
                    'updated_at' => '2026-08-08T06:05:00+00:00',
                ]],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeSnapshot(array $payload): string
    {
        $directory = storage_path('app/catalog-snapshots');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/import-test-'.uniqid('', true).'.json';
        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        return $path;
    }
}
