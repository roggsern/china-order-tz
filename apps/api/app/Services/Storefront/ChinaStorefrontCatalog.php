<?php

namespace App\Services\Storefront;

use App\Enums\CatalogOrigin;
use App\Http\Resources\CustomerProductCardResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Search\ChinaSellableProductQuery;
use App\Services\Search\SearchRelevance;
use App\Support\Catalog\CatalogNavigationCrosswalk;
use Database\Support\CatalogBible;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * ORDER FROM CHINA navigation catalog — CHINA_IMPORT only.
 * Excludes TZ stores, store-scoped categories, and inactive/unpublished products.
 */
class ChinaStorefrontCatalog
{
    /** Compact mega-menu featured strip — one desktop row inside ~640px content column. */
    public const MENU_FEATURED_PREVIEW_LIMIT = 4;

    public function __construct(
        private readonly CatalogNavigationCrosswalkResolver $crosswalkResolver,
        private readonly ChinaSellableProductQuery $chinaSellable,
        private readonly SearchRelevance $relevance,
    ) {}

    /**
     * Homepage featured collection roots — same navigation taxonomy as the mega menu,
     * limited to categories with at least one purchasable China-import product.
     *
     * @return Collection<int, Category>
     */
    public function featuredCollectionCategories(int $limit = 6): Collection
    {
        $limit = max(1, min(12, $limit));

        return $this->navigationCategories()
            ->filter(fn (Category $root) => $this->categoryHasPurchasableProducts($root->slug))
            ->take($limit)
            ->values();
    }

    private function categoryHasPurchasableProducts(string $categorySlug): bool
    {
        return $this->chinaPublishedProductQuery(
            $this->applyDiscoveryCategoryFilter(Product::query(), $categorySlug),
        )->exists();
    }

    /**
     * Navigation category tree for the ORDER FROM CHINA mega menu.
     *
     * Roots remain Catalog Bible ∩ active China roots.
     * Children:
     * - aggregate_of roots (e.g. Electronics) → product-aware Bible children
     * - department_slugs roots (e.g. fashion / beauty / home-care) → product-aware
     *   top-level department categories (self + descendants)
     * - otherwise → product-aware Bible children when defined
     *
     * @return Collection<int, Category>
     */
    public function navigationCategories(): Collection
    {
        $bibleRoots = collect(CatalogBible::categories());

        $roots = Category::query()
            ->where('is_active', true)
            ->where('origin', CatalogOrigin::China)
            ->whereNull('store_id')
            ->whereNull('parent_id')
            ->whereIn('slug', $bibleRoots->pluck('slug')->all())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $roots
            ->map(function (Category $root) use ($bibleRoots) {
                $definition = $bibleRoots->firstWhere('slug', $root->slug);
                $childDefinitions = $definition['children'] ?? [];
                $mapping = CatalogNavigationCrosswalk::forBibleSlug($root->slug) ?? [];

                if (! empty($mapping['aggregate_of'])) {
                    $visibleChildren = $this->crosswalkResolver->visibleBibleChildCategories(
                        $root->slug,
                        $childDefinitions,
                    );
                } elseif (! empty($mapping['department_slugs'])) {
                    $visibleChildren = $this->crosswalkResolver->visibleDepartmentChildCategories(
                        $root->slug,
                    );
                } else {
                    $visibleChildren = $this->crosswalkResolver->visibleBibleChildCategories(
                        $root->slug,
                        $childDefinitions,
                    );
                }

                $root->setRelation('children', $visibleChildren);

                return $root;
            })
            ->filter(function (Category $root) {
                return $this->crosswalkResolver->isBibleNodeVisible($root->slug)
                    || $root->children->isNotEmpty();
            })
            ->values();
    }

    /**
     * @return Collection<int, Brand>
     */
    public function brands(?string $categorySlug = null, int $limit = 24): Collection
    {
        $query = Brand::query()
            ->where('is_active', true)
            ->whereHas('products', fn (Builder $p) => $this->chinaPublishedProductQuery($p)
                ->when(filled($categorySlug), fn (Builder $p) => $this->applyDiscoveryCategoryFilter($p, $categorySlug)))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit);

        return $query->get(['id', 'name', 'slug', 'logo', 'is_featured', 'sort_order']);
    }

    public function products(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 12), 1), 48);
        $category = $filters['category'] ?? null;
        $brand = $filters['brand'] ?? null;
        $featured = $filters['featured'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        return $this->chinaPublishedProductQuery(Product::query())
            ->real()
            ->whereNull('store_id')
            ->with(CustomerProductCardResource::listingEagerLoads())
            ->withAvg(
                ['reviews as average_rating' => fn ($query) => $query->where('is_approved', true)],
                'rating',
            )
            ->withCount(
                ['reviews as review_count' => fn ($query) => $query->where('is_approved', true)],
            )
            ->when($search !== '', function (Builder $query) use ($search) {
                $this->relevance->applyProductMatchFilter($query, $search, ['include_store' => false]);
                $this->relevance->applyProductRelevanceOrder($query, $search, ['include_store' => false]);
            }, fn (Builder $query) => $query->latest())
            ->when(filled($category), fn (Builder $query) => $this->applyDiscoveryCategoryFilter($query, $category))
            ->when(filled($brand), function (Builder $query) use ($brand) {
                $query->where(function (Builder $q) use ($brand) {
                    $q->where('brand_id', $brand)
                        ->orWhereHas('brand', fn (Builder $b) => $b->where('slug', $brand));
                });
            })
            ->when(in_array($featured, ['1', 'true', 1, true], true), fn (Builder $q) => $q->where('is_featured', true))
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Mega-menu brand chips — same discovery rules as brands(), capped for chrome.
     *
     * @return Collection<int, Brand>
     */
    public function menuBrands(?string $categorySlug = null, int $limit = 12): Collection
    {
        return $this->brands($categorySlug, $limit);
    }

    /**
     * Mega-menu featured tiles — same sellable gates as listing, without listing-card
     * eager loads (variants/inventory/reviews) or paginator COUNT.
     *
     * Default limit is the compact desktop preview strip (one row).
     *
     * @param  array{category?: string|null, featured?: mixed, per_page?: int}  $filters
     * @return Collection<int, Product>
     */
    public function menuProducts(array $filters = []): Collection
    {
        $limit = min(max((int) ($filters['per_page'] ?? self::MENU_FEATURED_PREVIEW_LIMIT), 1), 12);
        $category = $filters['category'] ?? null;
        $featured = $filters['featured'] ?? null;

        return $this->chinaPublishedProductQuery(Product::query())
            ->real()
            ->whereNull('store_id')
            ->with([
                'brand:id,name,slug',
                'media' => fn ($query) => $query->images()->active()->ordered(),
                'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order'),
            ])
            ->latest()
            ->when(filled($category), fn (Builder $query) => $this->applyDiscoveryCategoryFilter($query, $category))
            ->when(in_array($featured, ['1', 'true', 1, true], true), fn (Builder $q) => $q->where('is_featured', true))
            ->limit($limit)
            ->get([
                'id',
                'slug',
                'name',
                'brand_id',
                'category_id',
                'commerce_channel_id',
                'is_featured',
                'created_at',
            ]);
    }

    private function applyDiscoveryCategoryFilter(Builder $query, string $category): Builder
    {
        if (CatalogNavigationCrosswalk::forBibleSlug($category) !== null) {
            $categoryIds = $this->crosswalkResolver->categoryIdsForBibleSlug($category);

            if ($categoryIds === []) {
                return $query->whereRaw('0 = 1');
            }

            return $query->whereIn('category_id', $categoryIds);
        }

        // Department / nested China category deep-links: full branch (self + descendants).
        $branchIds = $this->crosswalkResolver->resolveChinaCategoryBranchIds($category);
        if ($branchIds !== []) {
            return $query->whereIn('category_id', $branchIds);
        }

        return $query->whereRaw('0 = 1');
    }

    private function chinaPublishedProductQuery(Builder $query): Builder
    {
        return $this->chinaSellable->apply($query);
    }
}
