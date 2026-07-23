<?php

namespace App\Support\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\Category;
use Illuminate\Validation\ValidationException;

final class CategoryRelationshipRules
{
    /**
     * @throws ValidationException
     */
    public static function assertOriginStoreConsistency(string $origin, mixed $storeId): void
    {
        $originValue = $origin instanceof CatalogOrigin ? $origin->value : (string) $origin;
        $hasStore = $storeId !== null && $storeId !== '';

        if ($originValue === CatalogOrigin::China->value && $hasStore) {
            throw ValidationException::withMessages([
                'store_id' => ['China categories must not have a store_id.'],
            ]);
        }

        if ($originValue === CatalogOrigin::Tz->value && ! $hasStore) {
            throw ValidationException::withMessages([
                'store_id' => ['Tanzania categories require a store_id.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function assertParentRelationship(
        ?string $parentId,
        string $departmentId,
        ?string $categoryId = null,
    ): void {
        if ($parentId === null || $parentId === '') {
            return;
        }

        if ($categoryId !== null && $parentId === $categoryId) {
            throw ValidationException::withMessages([
                'parent_id' => ['A category cannot be its own parent.'],
            ]);
        }

        $parent = Category::query()->find($parentId);

        if ($parent === null) {
            return;
        }

        if ((string) $parent->department_id !== (string) $departmentId) {
            throw ValidationException::withMessages([
                'department_id' => ['Parent and child categories must belong to the same department.'],
            ]);
        }

        if ($categoryId !== null && self::isDescendantOf($parentId, $categoryId)) {
            throw ValidationException::withMessages([
                'parent_id' => ['Cannot set a descendant category as parent (would create a cycle).'],
            ]);
        }
    }

    /**
     * True when $nodeId is a descendant of $ancestorId (walks parent_id upward).
     */
    public static function isDescendantOf(string $nodeId, string $ancestorId): bool
    {
        $currentId = $nodeId;
        $guard = 0;

        while ($currentId !== '' && $guard++ < 100) {
            $node = Category::query()->find($currentId);

            if ($node === null || $node->parent_id === null) {
                return false;
            }

            if ((string) $node->parent_id === (string) $ancestorId) {
                return true;
            }

            $currentId = (string) $node->parent_id;
        }

        return false;
    }
}
