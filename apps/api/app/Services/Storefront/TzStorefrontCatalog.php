<?php

namespace App\Services\Storefront;

use App\Http\Resources\CustomerProductCardResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\Search\TzStorefrontProductCorpus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * BUY FROM TZ marketplace catalog — Store-scoped, TZ_LOCAL only.
 * Does not mix China import brands or catalog.
 *
 * Category branch contract (single authority for menu + category pages):
 * customer-visible products for category C in store S =
 * TzStorefrontProductCorpus(S) where category_id ∈ active descendants of C (including C)
 * within the same store.
 */
class TzStorefrontCatalog
{
    public function __construct(
        private readonly TzStorefrontProductCorpus $tzProductCorpus,
    ) {}

    /**
     * @return Collection<int, Store>
     */
    public function stores(): Collection
    {
        return Store::query()
            ->storefrontVisible()
            ->orderByRaw('COALESCE(storefront_sort_order, sort_order) asc')
            ->orderBy('name')
            ->get();
    }

    public function findStore(string $slug): Store
    {
        $store = Store::query()
            ->storefrontVisible()
            ->where('slug', $slug)
            ->first();

        if ($store === null) {
            throw new NotFoundHttpException('Store not found.');
        }

        return $store;
    }

    /**
     * Navigable root categories for mega-menu / store chrome.
     * A root appears iff itself or any active same-store descendant has a
     * customer-visible storefront product (TzStorefrontProductCorpus).
     *
     * @return Collection<int, Category>
     */
    public function categories(Store $store): Collection
    {
        $activeCategories = Category::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($activeCategories->isEmpty()) {
            return new Collection;
        }

        $childrenByParentId = $activeCategories
            ->groupBy(fn (Category $category) => (string) ($category->parent_id ?? ''))
            ->map(fn (BaseCollection $group) => $group->values());

        $populatedIds = $this->populatedCategoryIdSet($store);

        $navigableRoots = $activeCategories
            ->whereNull('parent_id')
            ->values()
            ->filter(function (Category $root) use ($childrenByParentId, $populatedIds) {
                return $this->branchContainsPopulatedCategory(
                    (string) $root->id,
                    $childrenByParentId,
                    $populatedIds,
                );
            })
            ->values();

        foreach ($navigableRoots as $root) {
            $children = $childrenByParentId->get((string) $root->id, collect())->values();
            $root->setRelation('children', new Collection($children->all()));
        }

        return new Collection($navigableRoots->all());
    }

    public function products(Store $store, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 48);
        $category = $filters['category'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        return $this->storeProductQuery($store)
            ->with(CustomerProductCardResource::listingEagerLoads())
            ->withAvg(
                ['reviews as average_rating' => fn ($query) => $query->where('is_approved', true)],
                'rating',
            )
            ->withCount(
                ['reviews as review_count' => fn ($query) => $query->where('is_approved', true)],
            )
            ->when($search !== '', function (Builder $query) use ($search) {
                $term = '%'.mb_strtolower($search).'%';
                $query->where(function (Builder $q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(short_description) LIKE ?', [$term]);
                });
            })
            ->when(filled($category), function (Builder $query) use ($category, $store) {
                $branchIds = $this->resolveCategoryBranchIds($store, (string) $category);
                if ($branchIds === []) {
                    $query->whereRaw('0 = 1');

                    return;
                }

                $query->whereIn('category_id', $branchIds);
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function product(Store $store, string $productSlug): Product
    {
        $product = $this->storeProductQuery($store)
            ->where('slug', $productSlug)
            ->with(array_merge([
                'commerceChannel:id,name,code,description,is_active',
                'category:id,name,slug,store_id',
                'brand:id,name,slug',
                'store:id,name,slug,code,theme_color,logo_path',
                'catalogProductType:id,name',
                'variants' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with(['product', 'attributeValues.attribute', 'inventories', 'inventory']),
            ], CustomerProductMediaResolver::catalogEagerLoads()))
            ->first();

        if ($product === null) {
            throw new NotFoundHttpException('Product not found for this store.');
        }

        return $product;
    }

    /**
     * Resolve any active same-store category by slug or id (root or nested).
     * Soft-deleted / inactive / other-store categories do not resolve.
     */
    public function findCategory(Store $store, string $categoryKey): Category
    {
        $category = $this->findActiveStoreCategory($store, $categoryKey);
        if ($category === null) {
            throw new NotFoundHttpException('Category not found for this store.');
        }

        return $category;
    }

    /**
     * Ancestors from storefront root → immediate parent (excludes $category).
     * Only active same-store parents are included.
     *
     * @return list<array{id: string, name: string, slug: string}>
     */
    public function categoryAncestors(Store $store, Category $category): array
    {
        if ($category->parent_id === null) {
            return [];
        }

        // One store-scoped load; walk parent pointers in memory (no per-ancestor queries).
        $byId = Category::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->get(['id', 'name', 'slug', 'parent_id'])
            ->keyBy(fn (Category $row) => (string) $row->id);

        $chain = [];
        $currentParentId = (string) $category->parent_id;
        $guard = 0;

        while ($currentParentId !== '' && $guard < 32) {
            $guard++;
            $parent = $byId->get($currentParentId);
            if ($parent === null) {
                break;
            }

            array_unshift($chain, [
                'id' => (string) $parent->id,
                'name' => (string) $parent->name,
                'slug' => (string) $parent->slug,
            ]);
            $currentParentId = $parent->parent_id !== null ? (string) $parent->parent_id : '';
        }

        return $chain;
    }

    /**
     * Active category C plus all active same-store descendants (multi-level).
     *
     * @return list<string>
     */
    public function resolveCategoryBranchIds(Store $store, string $categoryKey): array
    {
        $anchor = $this->findActiveStoreCategory($store, $categoryKey);
        if ($anchor === null) {
            return [];
        }

        return $this->activeDescendantIdsIncludingSelf($store, (string) $anchor->id);
    }

    /**
     * @return list<string>
     */
    public function activeDescendantIdsIncludingSelf(Store $store, string $categoryId): array
    {
        $ids = [$categoryId];
        $frontier = [$categoryId];

        while ($frontier !== []) {
            $children = Category::query()
                ->where('store_id', $store->id)
                ->where('is_active', true)
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();

            $frontier = [];
            foreach ($children as $childId) {
                $childId = (string) $childId;
                if (! in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $frontier[] = $childId;
                }
            }
        }

        return $ids;
    }

    private function findActiveStoreCategory(Store $store, string $categoryKey): ?Category
    {
        return Category::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($categoryKey) {
                $query->where('slug', $categoryKey)->orWhere('id', $categoryKey);
            })
            ->first();
    }

    /**
     * Category IDs that currently hold ≥1 corpus-visible product for the store.
     *
     * @return array<string, true>
     */
    private function populatedCategoryIdSet(Store $store): array
    {
        $ids = $this->storeProductQuery($store)
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return array_fill_keys($ids, true);
    }

    /**
     * @param  BaseCollection<string, BaseCollection<int, Category>>  $childrenByParentId
     * @param  array<string, true>  $populatedIds
     */
    private function branchContainsPopulatedCategory(
        string $rootId,
        BaseCollection $childrenByParentId,
        array $populatedIds,
    ): bool {
        $stack = [$rootId];

        while ($stack !== []) {
            $id = array_pop($stack);
            if (isset($populatedIds[$id])) {
                return true;
            }

            foreach ($childrenByParentId->get($id, collect()) as $child) {
                $stack[] = (string) $child->id;
            }
        }

        return false;
    }

    private function storeProductQuery(Store $store): Builder
    {
        return $this->tzProductCorpus->apply(Product::query(), $store);
    }
}
