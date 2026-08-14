<?php

namespace App\Support\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\Category;
use App\Models\Store;
use App\Models\StoreTaxonomyImportMap;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Resolves the TZ store category that should receive a China taxonomy import node.
 *
 * Preference order:
 * 1. Durable map (store_id + source_category_id)
 * 2. Deterministic import slug
 * 3. Compatible unclaimed store category (same store, name, hierarchy) — never by name alone across claimed targets
 *
 * Soft-deleted targets are never reused.
 */
final class TzTaxonomyImportCategoryResolver
{
    /**
     * @param  array<string, Category>  $sourceToTarget  already resolved parents in this import pass
     * @return array{
     *     category: ?Category,
     *     status: 'reuse'|'new',
     *     reason: 'map'|'deterministic_slug'|'compatible_existing'|null
     * }
     */
    public function resolve(
        Store $store,
        Category $source,
        array $sourceToTarget,
        bool $persistMap = false,
    ): array {
        $parentId = $this->resolvedParentId($source, $sourceToTarget);

        $mapped = $this->findMappedTarget($store, $source);
        if ($mapped !== null) {
            $this->alignParentIfSafe($mapped, $parentId);
            if ($persistMap) {
                $this->remember($store, $source, $mapped);
            }

            return ['category' => $mapped, 'status' => 'reuse', 'reason' => 'map'];
        }

        $deterministicSlug = TzTaxonomyImportIdentity::categorySlug(
            (string) $store->slug,
            (string) $source->slug,
        );

        $bySlug = Category::query()
            ->where('store_id', $store->id)
            ->where('origin', CatalogOrigin::Tz)
            ->where('slug', $deterministicSlug)
            ->first();

        if ($bySlug !== null) {
            $this->alignParentIfSafe($bySlug, $parentId);
            if ($persistMap) {
                $this->remember($store, $source, $bySlug);
            }

            return ['category' => $bySlug, 'status' => 'reuse', 'reason' => 'deterministic_slug'];
        }

        $compatible = $this->findCompatibleExisting($store, $source, $parentId);
        if ($compatible !== null) {
            $this->alignParentIfSafe($compatible, $parentId);
            if ($persistMap) {
                $this->remember($store, $source, $compatible);
            }

            return ['category' => $compatible, 'status' => 'reuse', 'reason' => 'compatible_existing'];
        }

        return ['category' => null, 'status' => 'new', 'reason' => null];
    }

    public function remember(Store $store, Category $source, Category $target): void
    {
        StoreTaxonomyImportMap::query()->updateOrCreate(
            [
                'store_id' => $store->id,
                'source_category_id' => $source->id,
            ],
            [
                'target_category_id' => $target->id,
            ],
        );
    }

    /**
     * @param  array<string, Category>  $sourceToTarget
     */
    private function resolvedParentId(Category $source, array $sourceToTarget): ?string
    {
        if ($source->parent_id === null) {
            return null;
        }

        $parentTarget = $sourceToTarget[$source->parent_id] ?? null;

        return $parentTarget instanceof Category ? $parentTarget->id : null;
    }

    private function findMappedTarget(Store $store, Category $source): ?Category
    {
        $map = StoreTaxonomyImportMap::query()
            ->where('store_id', $store->id)
            ->where('source_category_id', $source->id)
            ->first();

        if ($map === null) {
            return null;
        }

        $target = Category::query()
            ->whereKey($map->target_category_id)
            ->where('store_id', $store->id)
            ->where('origin', CatalogOrigin::Tz)
            ->first();

        if ($target === null) {
            // Stale map (soft-deleted / moved) — drop so later strategies can run.
            $map->delete();

            return null;
        }

        return $target;
    }

    private function findCompatibleExisting(Store $store, Category $source, ?string $expectedParentId): ?Category
    {
        $normalizedName = $this->normalizeName((string) $source->name);
        if ($normalizedName === '') {
            return null;
        }

        /** @var Collection<int, Category> $candidates */
        $candidates = Category::query()
            ->where('store_id', $store->id)
            ->where('origin', CatalogOrigin::Tz)
            ->where(function ($query) use ($expectedParentId) {
                if ($expectedParentId === null) {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', $expectedParentId);
                }
            })
            ->get()
            ->filter(fn (Category $category) => $this->normalizeName((string) $category->name) === $normalizedName)
            ->values();

        if ($candidates->count() !== 1) {
            // Zero or ambiguous same-name siblings — do not merge by name.
            return null;
        }

        /** @var Category $candidate */
        $candidate = $candidates->first();

        // Do not steal a category already claimed by a different China source.
        $claimedByOther = StoreTaxonomyImportMap::query()
            ->where('store_id', $store->id)
            ->where('target_category_id', $candidate->id)
            ->where('source_category_id', '!=', $source->id)
            ->exists();

        if ($claimedByOther) {
            return null;
        }

        return $candidate;
    }

    private function alignParentIfSafe(Category $target, ?string $expectedParentId): void
    {
        $currentParent = $target->parent_id;

        if ((string) ($currentParent ?? '') === (string) ($expectedParentId ?? '')) {
            return;
        }

        // Only auto-repair parent when target currently has no parent and import expects one,
        // or both sides are roots. Never re-parent a category that already has a different parent
        // (could break an established store hierarchy with products).
        if ($currentParent !== null && $expectedParentId !== null && (string) $currentParent !== (string) $expectedParentId) {
            return;
        }

        if ($currentParent === null && $expectedParentId !== null) {
            $target->parent_id = $expectedParentId;
            $target->save();
        }
    }

    private function normalizeName(string $name): string
    {
        return Str::lower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));
    }
}
