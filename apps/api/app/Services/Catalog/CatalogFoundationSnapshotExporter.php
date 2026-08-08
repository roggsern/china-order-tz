<?php

namespace App\Services\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductType;
use App\Models\CatalogProductTypeAttribute;
use App\Models\Category;
use App\Models\Department;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

/**
 * Read-only exporter for China admin catalog foundation snapshots.
 *
 * Does not write to the database. Does not touch storefront Bible/crosswalk.
 */
class CatalogFoundationSnapshotExporter
{
    public const FORMAT = 'catalog-foundation-snapshot';

    public const VERSION = 1;

    public const SCOPE_CHINA_ADMIN = 'china-admin';

    /**
     * @return array{
     *     format: string,
     *     version: int,
     *     generated_at: string,
     *     scope: string,
     *     source: array{app_env: string|null, app_url: string|null},
     *     counts: array<string, int>,
     *     tables: array<string, list<array<string, mixed>>>
     * }
     */
    public function build(string $scope = self::SCOPE_CHINA_ADMIN): array
    {
        if ($scope !== self::SCOPE_CHINA_ADMIN) {
            throw new InvalidArgumentException(
                "Unsupported snapshot scope [{$scope}]. Supported: ".self::SCOPE_CHINA_ADMIN,
            );
        }

        $categories = Category::withTrashed()
            ->where('origin', CatalogOrigin::China)
            ->whereNull('store_id')
            ->orderBy('id')
            ->get();

        $categoryIds = $categories->pluck('id')->all();

        $departmentIds = $categories
            ->pluck('department_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $departments = Department::withTrashed()
            ->whereIn('id', $departmentIds ?: ['00000000-0000-0000-0000-000000000000'])
            ->orderBy('id')
            ->get();

        $productTypes = CatalogProductType::withTrashed()
            ->whereIn('subcategory_id', $categoryIds ?: ['00000000-0000-0000-0000-000000000000'])
            ->orderBy('id')
            ->get();

        $productTypeIds = $productTypes->pluck('id')->all();

        $mappings = CatalogProductTypeAttribute::query()
            ->whereIn('catalog_product_type_id', $productTypeIds ?: ['00000000-0000-0000-0000-000000000000'])
            ->orderBy('id')
            ->get();

        $attributeIds = $mappings
            ->pluck('catalog_attribute_id')
            ->unique()
            ->values()
            ->all();

        $attributes = CatalogAttribute::withTrashed()
            ->whereIn('id', $attributeIds ?: ['00000000-0000-0000-0000-000000000000'])
            ->orderBy('id')
            ->get();

        $options = CatalogAttributeOption::query()
            ->whereIn('catalog_attribute_id', $attributeIds ?: ['00000000-0000-0000-0000-000000000000'])
            ->orderBy('id')
            ->get();

        $tables = [
            'departments' => $departments->map(fn (Department $row) => $this->mapDepartment($row))->values()->all(),
            'categories' => $categories->map(fn (Category $row) => $this->mapCategory($row))->values()->all(),
            'catalog_product_types' => $productTypes->map(fn (CatalogProductType $row) => $this->mapProductType($row))->values()->all(),
            'catalog_attributes' => $attributes->map(fn (CatalogAttribute $row) => $this->mapAttribute($row))->values()->all(),
            'catalog_attribute_options' => $options->map(fn (CatalogAttributeOption $row) => $this->mapOption($row))->values()->all(),
            'catalog_product_type_attributes' => $mappings->map(fn (CatalogProductTypeAttribute $row) => $this->mapMapping($row))->values()->all(),
        ];

        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'generated_at' => now()->toIso8601String(),
            'scope' => $scope,
            'source' => [
                'app_env' => config('app.env'),
                'app_url' => config('app.url'),
            ],
            'counts' => $counts,
            'tables' => $tables,
        ];
    }

    /**
     * @return array{
     *     path: string,
     *     checksum: string,
     *     bytes: int,
     *     snapshot: array<string, mixed>
     * }
     */
    public function exportToFile(?string $path = null, string $scope = self::SCOPE_CHINA_ADMIN): array
    {
        $snapshot = $this->build($scope);
        $resolvedPath = $this->resolveOutputPath($path);

        $directory = dirname($resolvedPath);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create snapshot directory [{$directory}].");
        }

        $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Failed to encode catalog foundation snapshot as JSON.');
        }

        $json .= "\n";

        if (file_put_contents($resolvedPath, $json) === false) {
            throw new RuntimeException("Unable to write snapshot file [{$resolvedPath}].");
        }

        return [
            'path' => $resolvedPath,
            'checksum' => hash('sha256', $json),
            'bytes' => strlen($json),
            'snapshot' => $snapshot,
        ];
    }

    private function resolveOutputPath(?string $path): string
    {
        if ($path === null || trim($path) === '') {
            $filename = 'catalog-foundation-'.now()->format('Y-m-d-His').'.json';

            return storage_path('app/catalog-snapshots/'.$filename);
        }

        $path = trim($path);

        if (is_dir($path) || str_ends_with($path, DIRECTORY_SEPARATOR) || str_ends_with($path, '/')) {
            $directory = rtrim($path, DIRECTORY_SEPARATOR.'/');
            $filename = 'catalog-foundation-'.now()->format('Y-m-d-His').'.json';

            return $directory.DIRECTORY_SEPARATOR.$filename;
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDepartment(Department $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'slug' => $row->slug,
            'icon' => $row->icon,
            'image' => $row->image,
            'description' => $row->description,
            'sort_order' => (int) $row->sort_order,
            'is_active' => (bool) $row->is_active,
            'created_at' => $this->timestamp($row->created_at),
            'updated_at' => $this->timestamp($row->updated_at),
            'deleted_at' => $this->timestamp($row->deleted_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCategory(Category $row): array
    {
        return [
            'id' => $row->id,
            'department_id' => $row->department_id,
            'parent_id' => $row->parent_id,
            'store_id' => $row->store_id,
            'origin' => $row->origin instanceof CatalogOrigin ? $row->origin->value : $row->origin,
            'product_type_id' => $row->product_type_id,
            'name' => $row->name,
            'slug' => $row->slug,
            'description' => $row->description,
            'image' => $row->image,
            'sort_order' => (int) $row->sort_order,
            'is_active' => (bool) $row->is_active,
            'created_at' => $this->timestamp($row->created_at),
            'updated_at' => $this->timestamp($row->updated_at),
            'deleted_at' => $this->timestamp($row->deleted_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapProductType(CatalogProductType $row): array
    {
        return [
            'id' => $row->id,
            'subcategory_id' => $row->subcategory_id,
            'name' => $row->name,
            'slug' => $row->slug,
            'image' => $row->image,
            'description' => $row->description,
            'sort_order' => (int) $row->sort_order,
            'is_active' => (bool) $row->is_active,
            'created_at' => $this->timestamp($row->created_at),
            'updated_at' => $this->timestamp($row->updated_at),
            'deleted_at' => $this->timestamp($row->deleted_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAttribute(CatalogAttribute $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'slug' => $row->slug,
            'type' => $row->type?->value ?? $row->type,
            'unit' => $row->unit,
            'is_filterable' => (bool) $row->is_filterable,
            'is_required' => (bool) $row->is_required,
            'sort_order' => (int) $row->sort_order,
            'is_active' => (bool) $row->is_active,
            'created_at' => $this->timestamp($row->created_at),
            'updated_at' => $this->timestamp($row->updated_at),
            'deleted_at' => $this->timestamp($row->deleted_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOption(CatalogAttributeOption $row): array
    {
        return [
            'id' => $row->id,
            'catalog_attribute_id' => $row->catalog_attribute_id,
            'value' => $row->value,
            'slug' => $row->slug,
            'sort_order' => (int) $row->sort_order,
            'created_at' => $this->timestamp($row->created_at),
            'updated_at' => $this->timestamp($row->updated_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMapping(CatalogProductTypeAttribute $row): array
    {
        return [
            'id' => $row->id,
            'catalog_product_type_id' => $row->catalog_product_type_id,
            'catalog_attribute_id' => $row->catalog_attribute_id,
            'is_required' => (bool) $row->is_required,
            'sort_order' => (int) $row->sort_order,
            'created_at' => $this->timestamp($row->created_at),
            'updated_at' => $this->timestamp($row->updated_at),
        ];
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        return Carbon::parse($value)->toIso8601String();
    }
}
