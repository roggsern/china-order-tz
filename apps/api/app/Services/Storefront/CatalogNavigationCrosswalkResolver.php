<?php

namespace App\Services\Storefront;

use App\Enums\CatalogOrigin;
use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\CatalogNavigationCrosswalk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves Catalog Bible navigation nodes to database category subtrees and
 * applies hybrid visibility rules (CHINA_IMPORT or NULL channel catalog products).
 */
class CatalogNavigationCrosswalkResolver
{
    /** @var array<string, list<string>> */
    private array $categoryIdCache = [];

    /**
     * Category IDs in the discovery scope for a Bible navigation slug.
     *
     * @return list<string>
     */
    public function categoryIdsForBibleSlug(string $bibleSlug): array
    {
        if (array_key_exists($bibleSlug, $this->categoryIdCache)) {
            return $this->categoryIdCache[$bibleSlug];
        }

        $mapping = CatalogNavigationCrosswalk::forBibleSlug($bibleSlug);

        if ($mapping === null) {
            return $this->categoryIdCache[$bibleSlug] = [];
        }

        if (! empty($mapping['aggregate_of'])) {
            $ids = collect($mapping['aggregate_of'])
                ->flatMap(fn (string $childSlug) => $this->categoryIdsForBibleSlug($childSlug))
                ->unique()
                ->values()
                ->all();

            return $this->categoryIdCache[$bibleSlug] = $ids;
        }

        $ids = collect();

        foreach ($mapping['department_slugs'] ?? [] as $departmentSlug) {
            $ids = $ids->merge(
                $this->categoryIdsForDepartmentSlug($departmentSlug),
            );
        }

        foreach ($mapping['category_slugs'] ?? [] as $categorySlug) {
            $ids = $ids->merge(
                $this->categoryIdsForCategorySlug($categorySlug),
            );
        }

        foreach ($mapping['exclude_category_slugs'] ?? [] as $excludeSlug) {
            $excludeIds = $this->categoryIdsForCategorySlug($excludeSlug);
            $ids = $ids->reject(fn (string $id) => in_array($id, $excludeIds, true));
        }

        $ids = $ids
            ->reject(fn (string $id) => $this->isExcludedCategoryId($id))
            ->unique()
            ->values();

        return $this->categoryIdCache[$bibleSlug] = $ids->all();
    }

    public function isBibleNodeVisible(string $bibleSlug): bool
    {
        $mapping = CatalogNavigationCrosswalk::forBibleSlug($bibleSlug);

        if ($mapping === null) {
            return false;
        }

        if ($mapping === []) {
            return false;
        }

        if (! empty($mapping['aggregate_of'])) {
            foreach ($mapping['aggregate_of'] as $childSlug) {
                if ($this->isBibleNodeVisible($childSlug)) {
                    return true;
                }
            }

            return false;
        }

        $categoryIds = $this->categoryIdsForBibleSlug($bibleSlug);

        if ($categoryIds === []) {
            return false;
        }

        return $this->hasNavigationVisibleProductInCategories($categoryIds);
    }

    /**
     * @param  list<string>  $categoryIds
     */
    public function hasNavigationVisibleProductInCategories(array $categoryIds): bool
    {
        if ($categoryIds === []) {
            return false;
        }

        return $this->navigationVisibleProductQuery(Product::query())
            ->whereIn('category_id', $categoryIds)
            ->exists();
    }

    public function navigationVisibleProductQuery(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_demo', false)
            ->where('lifecycle_status', ProductLifecycleStatus::Active)
            ->where('visibility', ProductVisibility::Public)
            ->whereNull('store_id')
            ->where(function (Builder $channel) {
                $channel->whereNull('commerce_channel_id')
                    ->orWhereHas(
                        'commerceChannel',
                        fn (Builder $q) => $q->where('code', CommerceChannelCode::ChinaImport->value),
                    );
            });
    }

    /**
     * Bible child slugs under a root that should appear in navigation chrome.
     *
     * @param  list<array{name: string, slug: string, sort_order: int}>  $childDefinitions
     * @return Collection<int, Category>
     */
    public function visibleBibleChildCategories(string $rootSlug, array $childDefinitions): Collection
    {
        $visibleSlugs = collect($childDefinitions)
            ->pluck('slug')
            ->filter(fn (string $slug) => $this->isBibleNodeVisible($slug))
            ->values()
            ->all();

        if ($visibleSlugs === []) {
            return collect();
        }

        $slugOnly = CatalogNavigationCrosswalk::SLUG_ONLY_BIBLE_CHILDREN;

        return Category::query()
            ->whereIn('slug', $visibleSlugs)
            ->whereNull('store_id')
            ->where('origin', CatalogOrigin::China)
            ->where(function (Builder $query) use ($slugOnly) {
                $query->where('is_active', true)
                    ->orWhereIn('slug', $slugOnly);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Product-aware department subcategories for a Bible root mapped via department_slugs.
     *
     * Top-level department categories (parent_id null) are advertised when their
     * branch (self + descendants) holds ≥1 navigation-visible China product.
     * Soft-deleted categories are excluded (SoftDeletes). Structural inactive
     * parents remain eligible when an active descendant branch is populated —
     * matching the seeded China department taxonomy pattern.
     *
     * Performance: one department category load + one distinct populated-id query.
     *
     * @return Collection<int, Category>
     */
    public function visibleDepartmentChildCategories(string $bibleRootSlug): Collection
    {
        $mapping = CatalogNavigationCrosswalk::forBibleSlug($bibleRootSlug);
        $departmentSlugs = $mapping['department_slugs'] ?? [];

        if ($departmentSlugs === []) {
            return collect();
        }

        $categories = Category::query()
            ->whereNull('store_id')
            ->where('origin', CatalogOrigin::China)
            ->whereHas('department', fn (Builder $q) => $q->whereIn('slug', $departmentSlugs))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'parent_id', 'is_active', 'department_id', 'sort_order', 'origin']);

        if ($categories->isEmpty()) {
            return collect();
        }

        $excluded = array_fill_keys(
            Category::query()
                ->whereIn('slug', CatalogNavigationCrosswalk::EXCLUDED_CATEGORY_SLUGS)
                ->pluck('id')
                ->all(),
            true,
        );

        $categories = $categories->reject(
            fn (Category $category) => isset($excluded[(string) $category->id]),
        );

        $allIds = $categories->map(fn (Category $c) => (string) $c->id)->all();
        $populated = $this->navigationVisibleProductQuery(Product::query())
            ->whereIn('category_id', $allIds)
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id')
            ->mapWithKeys(fn ($id) => [(string) $id => true])
            ->all();

        if ($populated === []) {
            return collect();
        }

        $childrenByParent = [];
        foreach ($categories as $category) {
            if ($category->parent_id === null) {
                continue;
            }
            $parentKey = (string) $category->parent_id;
            $childrenByParent[$parentKey][] = (string) $category->id;
        }

        $branchHasProduct = function (string $rootId) use ($childrenByParent, $populated): bool {
            $frontier = [$rootId];
            $seen = [$rootId => true];

            while ($frontier !== []) {
                $current = array_pop($frontier);
                if (isset($populated[$current])) {
                    return true;
                }
                foreach ($childrenByParent[$current] ?? [] as $childId) {
                    if (! isset($seen[$childId])) {
                        $seen[$childId] = true;
                        $frontier[] = $childId;
                    }
                }
            }

            return false;
        };

        return $categories
            ->filter(function (Category $category) use ($branchHasProduct) {
                if ($category->parent_id !== null) {
                    return false;
                }

                return $branchHasProduct((string) $category->id);
            })
            ->values();
    }

    /**
     * Category + descendants for a China storefront category slug or id.
     * Used for department-child deep links outside the Bible child map.
     *
     * @return list<string>
     */
    public function resolveChinaCategoryBranchIds(string $categoryKey): array
    {
        if (in_array($categoryKey, CatalogNavigationCrosswalk::EXCLUDED_CATEGORY_SLUGS, true)) {
            return [];
        }

        $anchor = Category::query()
            ->whereNull('store_id')
            ->where('origin', CatalogOrigin::China)
            ->where(function (Builder $query) use ($categoryKey) {
                $query->where('slug', $categoryKey)->orWhere('id', $categoryKey);
            })
            ->first();

        if ($anchor === null || $this->isExcludedCategoryId((string) $anchor->id)) {
            return [];
        }

        return $this->collectDescendantCategoryIds($anchor);
    }

    /**
     * @return list<string>
     */
    private function categoryIdsForDepartmentSlug(string $departmentSlug): array
    {
        return Category::query()
            ->whereNull('store_id')
            ->where('origin', CatalogOrigin::China)
            ->whereHas('department', fn (Builder $q) => $q->where('slug', $departmentSlug))
            ->pluck('id')
            ->reject(fn (string $id) => $this->isExcludedCategoryId($id))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function categoryIdsForCategorySlug(string $categorySlug): array
    {
        return $this->resolveChinaCategoryBranchIds($categorySlug);
    }

    /**
     * @return list<string>
     */
    private function collectDescendantCategoryIds(Category $anchor): array
    {
        $ids = collect([$anchor->id]);
        $queue = collect([$anchor->id]);

        while ($queue->isNotEmpty()) {
            $children = Category::query()
                ->whereIn('parent_id', $queue->all())
                ->whereNull('store_id')
                ->where('origin', CatalogOrigin::China)
                ->pluck('id');

            $children = $children->reject(fn (string $id) => $this->isExcludedCategoryId($id));

            $newIds = $children->diff($ids);
            $ids = $ids->merge($newIds);
            $queue = $newIds->values();
        }

        return $ids->unique()->values()->all();
    }

    private function isExcludedCategoryId(string $categoryId): bool
    {
        static $excludedIds = null;

        if ($excludedIds === null) {
            $excludedIds = Category::query()
                ->whereIn('slug', CatalogNavigationCrosswalk::EXCLUDED_CATEGORY_SLUGS)
                ->pluck('id')
                ->all();
        }

        return in_array($categoryId, $excludedIds, true);
    }
}
