<?php

namespace App\Services\Catalog;

use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductType;
use App\Models\CatalogProductTypeAttribute;
use App\Models\Category;
use App\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * Imports catalog-foundation-snapshot JSON into local/dev databases.
 *
 * Default is dry-run (no writes). Production execute is blocked unless forced.
 */
class CatalogFoundationSnapshotImporter
{
    public const MODE_UPSERT = 'upsert';

    public const MODE_REPLACE_FOUNDATION = 'replace-foundation';

    /**
     * @param  list<string>  $allowEnvs
     * @return array{
     *     dry_run: bool,
     *     mode: string,
     *     scope: string,
     *     path: string,
     *     changes: array<string, array<string, list<string>>>,
     *     counts: array<string, array<string, int>>,
     *     warnings: list<string>
     * }
     */
    public function import(
        string $path,
        bool $execute = false,
        string $mode = self::MODE_UPSERT,
        string $scope = CatalogFoundationSnapshotExporter::SCOPE_CHINA_ADMIN,
        array $allowEnvs = ['local'],
        bool $forceEnv = false,
    ): array {
        if (! in_array($mode, [self::MODE_UPSERT, self::MODE_REPLACE_FOUNDATION], true)) {
            throw new InvalidArgumentException(
                "Unsupported import mode [{$mode}]. Supported: upsert, replace-foundation.",
            );
        }

        if ($scope !== CatalogFoundationSnapshotExporter::SCOPE_CHINA_ADMIN) {
            throw new InvalidArgumentException(
                "Unsupported import scope [{$scope}]. Supported: china-admin.",
            );
        }

        $this->assertEnvironmentAllowsImport($execute, $allowEnvs, $forceEnv);

        $snapshot = $this->loadSnapshot($path);
        $this->validateSnapshot($snapshot, $scope);

        $plan = $this->buildPlan($snapshot, $mode);

        if (! $execute) {
            return [
                'dry_run' => true,
                'mode' => $mode,
                'scope' => $scope,
                'path' => $path,
                'changes' => $plan['changes'],
                'counts' => $this->summarizeCounts($plan['changes']),
                'warnings' => $plan['warnings'],
            ];
        }

        DB::transaction(function () use ($snapshot, $mode, $plan) {
            $this->applyDepartments($snapshot['tables']['departments']);
            $this->applyCategories($snapshot['tables']['categories']);
            $this->applyAttributes($snapshot['tables']['catalog_attributes']);
            $this->applyOptions($snapshot['tables']['catalog_attribute_options']);
            $this->applyProductTypes($snapshot['tables']['catalog_product_types']);
            $this->applyMappings(
                $snapshot['tables']['catalog_product_type_attributes'],
                $snapshot['tables']['catalog_product_types'],
                $mode,
            );
        });

        return [
            'dry_run' => false,
            'mode' => $mode,
            'scope' => $scope,
            'path' => $path,
            'changes' => $plan['changes'],
            'counts' => $this->summarizeCounts($plan['changes']),
            'warnings' => $plan['warnings'],
        ];
    }

    /**
     * @param  list<string>  $allowEnvs
     */
    private function assertEnvironmentAllowsImport(bool $execute, array $allowEnvs, bool $forceEnv): void
    {
        $env = (string) config('app.env');
        $allowEnvs = array_values(array_filter(array_map('strval', $allowEnvs)));

        if ($allowEnvs === []) {
            $allowEnvs = ['local'];
        }

        if (! $execute) {
            return;
        }

        if ($env === 'production' && ! $forceEnv) {
            throw new RuntimeException(
                'Refusing to execute catalog snapshot import while APP_ENV=production. Pass --force-env to override.',
            );
        }

        if (! in_array($env, $allowEnvs, true) && ! $forceEnv) {
            throw new RuntimeException(
                "Refusing to execute catalog snapshot import in APP_ENV={$env}. Allowed: "
                .implode(', ', $allowEnvs)
                .'. Pass --force-env to override.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSnapshot(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Snapshot file not found: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            throw new RuntimeException("Snapshot file is empty or unreadable: {$path}");
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Snapshot JSON is invalid: '.$exception->getMessage(), 0, $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Snapshot JSON must decode to an object/array.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function validateSnapshot(array $snapshot, string $requestedScope): void
    {
        if (($snapshot['format'] ?? null) !== CatalogFoundationSnapshotExporter::FORMAT) {
            throw new RuntimeException(
                'Unsupported snapshot format. Expected '.CatalogFoundationSnapshotExporter::FORMAT.'.',
            );
        }

        if ((int) ($snapshot['version'] ?? 0) !== CatalogFoundationSnapshotExporter::VERSION) {
            throw new RuntimeException(
                'Unsupported snapshot version. Expected '.CatalogFoundationSnapshotExporter::VERSION.'.',
            );
        }

        $snapshotScope = (string) ($snapshot['scope'] ?? '');
        if ($snapshotScope !== $requestedScope) {
            throw new RuntimeException(
                "Snapshot scope [{$snapshotScope}] does not match requested scope [{$requestedScope}].",
            );
        }

        if (! isset($snapshot['tables']) || ! is_array($snapshot['tables'])) {
            throw new RuntimeException('Snapshot is missing tables payload.');
        }

        $requiredTables = [
            'departments',
            'categories',
            'catalog_product_types',
            'catalog_attributes',
            'catalog_attribute_options',
            'catalog_product_type_attributes',
        ];

        foreach ($requiredTables as $table) {
            if (! array_key_exists($table, $snapshot['tables']) || ! is_array($snapshot['tables'][$table])) {
                throw new RuntimeException("Snapshot missing required table [{$table}].");
            }

            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Database is missing required table [{$table}].");
            }

            $this->assertUniqueIds($table, $snapshot['tables'][$table]);
        }

        $departmentIds = collect($snapshot['tables']['departments'])->pluck('id')->filter()->all();
        $categoryIds = collect($snapshot['tables']['categories'])->pluck('id')->filter()->all();
        $attributeIds = collect($snapshot['tables']['catalog_attributes'])->pluck('id')->filter()->all();
        $productTypeIds = collect($snapshot['tables']['catalog_product_types'])->pluck('id')->filter()->all();

        foreach ($snapshot['tables']['categories'] as $index => $row) {
            $departmentId = $row['department_id'] ?? null;
            if ($departmentId !== null && ! in_array($departmentId, $departmentIds, true)) {
                throw new RuntimeException(
                    "Category[{$index}] references missing department_id [{$departmentId}] in snapshot.",
                );
            }

            $parentId = $row['parent_id'] ?? null;
            if ($parentId !== null && ! in_array($parentId, $categoryIds, true)) {
                throw new RuntimeException(
                    "Category[{$index}] references missing parent_id [{$parentId}] in snapshot.",
                );
            }
        }

        foreach ($snapshot['tables']['catalog_product_types'] as $index => $row) {
            $subcategoryId = $row['subcategory_id'] ?? null;
            if ($subcategoryId === null || ! in_array($subcategoryId, $categoryIds, true)) {
                throw new RuntimeException(
                    "Product type[{$index}] references missing subcategory_id [{$subcategoryId}] in snapshot.",
                );
            }
        }

        foreach ($snapshot['tables']['catalog_attribute_options'] as $index => $row) {
            $attributeId = $row['catalog_attribute_id'] ?? null;
            if ($attributeId === null || ! in_array($attributeId, $attributeIds, true)) {
                throw new RuntimeException(
                    "Attribute option[{$index}] references missing catalog_attribute_id [{$attributeId}] in snapshot.",
                );
            }
        }

        foreach ($snapshot['tables']['catalog_product_type_attributes'] as $index => $row) {
            $typeId = $row['catalog_product_type_id'] ?? null;
            $attributeId = $row['catalog_attribute_id'] ?? null;
            if ($typeId === null || ! in_array($typeId, $productTypeIds, true)) {
                throw new RuntimeException(
                    "Mapping[{$index}] references missing catalog_product_type_id [{$typeId}] in snapshot.",
                );
            }
            if ($attributeId === null || ! in_array($attributeId, $attributeIds, true)) {
                throw new RuntimeException(
                    "Mapping[{$index}] references missing catalog_attribute_id [{$attributeId}] in snapshot.",
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function assertUniqueIds(string $table, array $rows): void
    {
        $ids = [];
        foreach ($rows as $index => $row) {
            $id = $row['id'] ?? null;
            if (! is_string($id) || $id === '') {
                throw new RuntimeException("Snapshot table [{$table}] row[{$index}] is missing id.");
            }
            if (isset($ids[$id])) {
                throw new RuntimeException("Snapshot table [{$table}] contains duplicate id [{$id}].");
            }
            $ids[$id] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *     changes: array<string, array<string, list<string>>>,
     *     warnings: list<string>
     * }
     */
    private function buildPlan(array $snapshot, string $mode): array
    {
        $warnings = [];
        $changes = [
            'departments' => $this->planSoftDeletableUpserts(
                Department::class,
                $snapshot['tables']['departments'],
                ['name', 'slug', 'icon', 'image', 'description', 'sort_order', 'is_active'],
            ),
            'categories' => $this->planSoftDeletableUpserts(
                Category::class,
                $this->orderCategories($snapshot['tables']['categories']),
                [
                    'department_id',
                    'parent_id',
                    'store_id',
                    'origin',
                    'product_type_id',
                    'name',
                    'slug',
                    'description',
                    'image',
                    'sort_order',
                    'is_active',
                ],
            ),
            'catalog_attributes' => $this->planSoftDeletableUpserts(
                CatalogAttribute::class,
                $snapshot['tables']['catalog_attributes'],
                [
                    'name',
                    'slug',
                    'type',
                    'unit',
                    'is_filterable',
                    'is_required',
                    'sort_order',
                    'is_active',
                ],
            ),
            'catalog_attribute_options' => $this->planHardUpserts(
                CatalogAttributeOption::class,
                $snapshot['tables']['catalog_attribute_options'],
                ['catalog_attribute_id', 'value', 'slug', 'sort_order'],
            ),
            'catalog_product_types' => $this->planSoftDeletableUpserts(
                CatalogProductType::class,
                $snapshot['tables']['catalog_product_types'],
                [
                    'subcategory_id',
                    'name',
                    'slug',
                    'image',
                    'description',
                    'sort_order',
                    'is_active',
                ],
            ),
            'catalog_product_type_attributes' => $this->planHardUpserts(
                CatalogProductTypeAttribute::class,
                $snapshot['tables']['catalog_product_type_attributes'],
                [
                    'catalog_product_type_id',
                    'catalog_attribute_id',
                    'is_required',
                    'sort_order',
                ],
            ),
        ];

        $changes['catalog_product_type_attributes']['delete_local_only'] = [];

        if ($mode === self::MODE_REPLACE_FOUNDATION) {
            $snapshotMappingIds = collect($snapshot['tables']['catalog_product_type_attributes'])
                ->pluck('id')
                ->all();
            $snapshotTypeIds = collect($snapshot['tables']['catalog_product_types'])
                ->pluck('id')
                ->all();

            $localOnlyQuery = CatalogProductTypeAttribute::query()
                ->whereIn(
                    'catalog_product_type_id',
                    $snapshotTypeIds ?: ['00000000-0000-0000-0000-000000000000'],
                );

            if ($snapshotMappingIds !== []) {
                $localOnlyQuery->whereNotIn('id', $snapshotMappingIds);
            }

            $localOnly = $localOnlyQuery->pluck('id')->all();
            $changes['catalog_product_type_attributes']['delete_local_only'] = array_values($localOnly);

            if ($localOnly !== []) {
                $warnings[] = 'replace-foundation will remove '
                    .count($localOnly)
                    .' local-only product-type attribute mapping(s).';
            }
        }

        return [
            'changes' => $changes,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $compareFields
     * @return array{create: list<string>, update: list<string>, restore: list<string>, soft_delete: list<string>}
     */
    private function planSoftDeletableUpserts(string $modelClass, array $rows, array $compareFields): array
    {
        $plan = [
            'create' => [],
            'update' => [],
            'restore' => [],
            'soft_delete' => [],
        ];

        foreach ($rows as $row) {
            $id = (string) $row['id'];
            /** @var Model|null $existing */
            $existing = $modelClass::withTrashed()->find($id);
            $snapshotDeleted = $this->normalizeTimestamp($row['deleted_at'] ?? null);

            if ($existing === null) {
                $plan['create'][] = $id;
                if ($snapshotDeleted !== null) {
                    $plan['soft_delete'][] = $id;
                }

                continue;
            }

            $localDeleted = $this->normalizeTimestamp($existing->getAttribute('deleted_at'));

            if ($localDeleted !== null && $snapshotDeleted === null) {
                $plan['restore'][] = $id;
            }

            if ($localDeleted === null && $snapshotDeleted !== null) {
                $plan['soft_delete'][] = $id;
            }

            if ($localDeleted !== null && $snapshotDeleted !== null && $localDeleted !== $snapshotDeleted) {
                $plan['soft_delete'][] = $id;
            }

            if ($this->rowNeedsUpdate($existing, $row, $compareFields)) {
                $plan['update'][] = $id;
            }
        }

        return $plan;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $compareFields
     * @return array{create: list<string>, update: list<string>, restore: list<string>, soft_delete: list<string>}
     */
    private function planHardUpserts(string $modelClass, array $rows, array $compareFields): array
    {
        $plan = [
            'create' => [],
            'update' => [],
            'restore' => [],
            'soft_delete' => [],
        ];

        foreach ($rows as $row) {
            $id = (string) $row['id'];
            /** @var Model|null $existing */
            $existing = $modelClass::query()->find($id);

            if ($existing === null) {
                $plan['create'][] = $id;

                continue;
            }

            if ($this->rowNeedsUpdate($existing, $row, $compareFields)) {
                $plan['update'][] = $id;
            }
        }

        return $plan;
    }

    /**
     * @param  list<string>  $compareFields
     */
    private function rowNeedsUpdate(Model $existing, array $row, array $compareFields): bool
    {
        foreach ($compareFields as $field) {
            $incoming = $row[$field] ?? null;
            $current = $existing->getAttribute($field);

            if ($current instanceof \BackedEnum) {
                $current = $current->value;
            }

            if ($this->normalizeComparable($current) !== $this->normalizeComparable($incoming)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeComparable(mixed $value): mixed
    {
        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        return Carbon::parse((string) $value)->toIso8601String();
    }

    /**
     * @param  list<array<string, mixed>>  $categories
     * @return list<array<string, mixed>>
     */
    private function orderCategories(array $categories): array
    {
        $byId = [];
        foreach ($categories as $category) {
            $byId[(string) $category['id']] = $category;
        }

        $ordered = [];
        $visited = [];

        $visit = function (string $id) use (&$visit, &$ordered, &$visited, $byId): void {
            if (isset($visited[$id])) {
                return;
            }

            if (! isset($byId[$id])) {
                return;
            }

            $visited[$id] = true;
            $parentId = $byId[$id]['parent_id'] ?? null;
            if (is_string($parentId) && $parentId !== '') {
                $visit($parentId);
            }

            $ordered[] = $byId[$id];
        };

        foreach ($byId as $id => $_row) {
            $visit($id);
        }

        return $ordered;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function applyDepartments(array $rows): void
    {
        foreach ($rows as $row) {
            $this->upsertSoftDeletable(Department::class, $row, [
                'name', 'slug', 'icon', 'image', 'description', 'sort_order', 'is_active',
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function applyCategories(array $rows): void
    {
        foreach ($this->orderCategories($rows) as $row) {
            $this->upsertSoftDeletable(Category::class, $row, [
                'department_id',
                'parent_id',
                'store_id',
                'origin',
                'product_type_id',
                'name',
                'slug',
                'description',
                'image',
                'sort_order',
                'is_active',
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function applyAttributes(array $rows): void
    {
        foreach ($rows as $row) {
            $this->upsertSoftDeletable(CatalogAttribute::class, $row, [
                'name',
                'slug',
                'type',
                'unit',
                'is_filterable',
                'is_required',
                'sort_order',
                'is_active',
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function applyOptions(array $rows): void
    {
        foreach ($rows as $row) {
            $this->upsertHard(CatalogAttributeOption::class, $row, [
                'catalog_attribute_id', 'value', 'slug', 'sort_order',
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function applyProductTypes(array $rows): void
    {
        foreach ($rows as $row) {
            $this->upsertSoftDeletable(CatalogProductType::class, $row, [
                'subcategory_id',
                'name',
                'slug',
                'image',
                'description',
                'sort_order',
                'is_active',
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $mappingRows
     * @param  list<array<string, mixed>>  $productTypeRows
     */
    private function applyMappings(array $mappingRows, array $productTypeRows, string $mode): void
    {
        foreach ($mappingRows as $row) {
            $this->upsertHard(CatalogProductTypeAttribute::class, $row, [
                'catalog_product_type_id',
                'catalog_attribute_id',
                'is_required',
                'sort_order',
            ]);
        }

        if ($mode !== self::MODE_REPLACE_FOUNDATION) {
            return;
        }

        $snapshotMappingIds = collect($mappingRows)->pluck('id')->all();
        $snapshotTypeIds = collect($productTypeRows)->pluck('id')->all();

        $query = CatalogProductTypeAttribute::query()
            ->whereIn(
                'catalog_product_type_id',
                $snapshotTypeIds ?: ['00000000-0000-0000-0000-000000000000'],
            );

        if ($snapshotMappingIds !== []) {
            $query->whereNotIn('id', $snapshotMappingIds);
        }

        $query->delete();
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $fields
     */
    private function upsertSoftDeletable(string $modelClass, array $row, array $fields): void
    {
        $id = (string) $row['id'];
        /** @var Model $model */
        $model = $modelClass::withTrashed()->find($id) ?? new $modelClass;

        $attributes = [];
        foreach ($fields as $field) {
            $attributes[$field] = $row[$field] ?? null;
        }

        $attributes['id'] = $id;
        $attributes['created_at'] = $this->parseTimestamp($row['created_at'] ?? null) ?? now();
        $attributes['updated_at'] = $this->parseTimestamp($row['updated_at'] ?? null) ?? now();
        $attributes['deleted_at'] = $this->parseTimestamp($row['deleted_at'] ?? null);

        $model->forceFill($attributes);
        $model->save();
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $fields
     */
    private function upsertHard(string $modelClass, array $row, array $fields): void
    {
        $id = (string) $row['id'];
        /** @var Model $model */
        $model = $modelClass::query()->find($id) ?? new $modelClass;

        $attributes = [];
        foreach ($fields as $field) {
            $attributes[$field] = $row[$field] ?? null;
        }

        $attributes['id'] = $id;
        $attributes['created_at'] = $this->parseTimestamp($row['created_at'] ?? null) ?? now();
        $attributes['updated_at'] = $this->parseTimestamp($row['updated_at'] ?? null) ?? now();

        $model->forceFill($attributes);
        $model->save();
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    /**
     * @param  array<string, array<string, list<string>>>  $changes
     * @return array<string, array<string, int>>
     */
    private function summarizeCounts(array $changes): array
    {
        $summary = [];

        foreach ($changes as $table => $actions) {
            $summary[$table] = [];
            foreach ($actions as $action => $ids) {
                $summary[$table][$action] = count($ids);
            }
        }

        return $summary;
    }
}
