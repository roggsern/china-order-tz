<?php

namespace App\Services\Storefront;

use App\Enums\CatalogOrigin;
use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\VariantPriceType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\Inventory\CatalogStockPresenter;
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
    public function __construct(
        private readonly CatalogNavigationCrosswalkResolver $crosswalkResolver,
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

                $visibleChildren = $this->crosswalkResolver->visibleBibleChildCategories(
                    $root->slug,
                    $childDefinitions,
                );

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
            ->with(array_merge([
                'commerceChannel:id,name,code',
                'category:id,name,slug',
                'brand:id,name,slug',
                'catalogProductType:id,name',
            ], CustomerProductMediaResolver::catalogEagerLoads(), CatalogStockPresenter::catalogListingEagerLoads()))
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
                        ->orWhereRaw('LOWER(COALESCE(short_description, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(sku, \'\')) LIKE ?', [$term])
                        ->orWhereHas(
                            'brand',
                            fn (Builder $brandQuery) => $brandQuery->whereRaw('LOWER(name) LIKE ?', [$term]),
                        )
                        ->orWhereHas(
                            'catalogProductType',
                            fn (Builder $typeQuery) => $typeQuery->whereRaw('LOWER(name) LIKE ?', [$term]),
                        );
                });
            })
            ->when(filled($category), fn (Builder $query) => $this->applyDiscoveryCategoryFilter($query, $category))
            ->when(filled($brand), function (Builder $query) use ($brand) {
                $query->where(function (Builder $q) use ($brand) {
                    $q->where('brand_id', $brand)
                        ->orWhereHas('brand', fn (Builder $b) => $b->where('slug', $brand));
                });
            })
            ->when(in_array($featured, ['1', 'true', 1, true], true), fn (Builder $q) => $q->where('is_featured', true))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
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

        return $query->where(function (Builder $q) use ($category) {
            $q->where('category_id', $category)
                ->orWhereHas('category', function (Builder $c) use ($category) {
                    $c->whereNull('store_id')
                        ->where(function (Builder $inner) use ($category) {
                            $inner->where('slug', $category)
                                ->orWhere('id', $category)
                                ->orWhereHas('parent', fn (Builder $p) => $p->where('slug', $category));
                        });
                });
        });
    }

    private function chinaPublishedProductQuery(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_demo', false)
            ->where('lifecycle_status', ProductLifecycleStatus::Active)
            ->where('visibility', ProductVisibility::Public)
            ->whereNull('store_id')
            ->whereHas('commerceChannel', fn (Builder $q) => $q->where('code', CommerceChannelCode::ChinaImport->value))
            ->whereHas('shippingOptions', fn (Builder $q) => $q->available()->where('price', '>', 0))
            ->where(function (Builder $q) {
                $q->whereHas('variants', fn (Builder $variant) => $this->applySellableVariantConstraints($variant))
                    ->orWhere(function (Builder $simple) {
                        $simple->where('price', '>', 0)
                            ->where(function (Builder $stock) {
                                $stock->whereHas('inventory', fn (Builder $inventory) => $inventory->whereNull('product_variant_id'))
                                    ->orWhereHas('chinaCommercialStocks', function (Builder $commercial) {
                                        $commercial->whereNull('product_variant_id')
                                            ->where('available_quantity', '>', 0);
                                    });
                            })
                            ->whereDoesntHave('variants', fn (Builder $variant) => $this->applySellableVariantConstraints($variant));
                    });
            });
    }

    /**
     * Mirrors ProductPurchasabilityPolicy sellable-variant rules for listing safety.
     */
    private function applySellableVariantConstraints(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $variant) {
                $variant->where(function (Builder $priced) {
                    $priced->whereNotNull('price')->where('price', '>', 0);
                })->orWhereHas('prices', function (Builder $prices) {
                    $prices->ofType(VariantPriceType::Retail)
                        ->active()
                        ->where('amount', '>', 0);
                });
            })
            ->where(function (Builder $variant) {
                $variant->whereHas('inventories', function (Builder $inventory) {
                    $inventory->where('warehouse_code', 'MAIN')->where('is_active', true);
                })
                    ->orWhereHas('inventory')
                    ->orWhereHas('chinaCommercialStock', function (Builder $commercial) {
                        $commercial->where('available_quantity', '>', 0);
                    });
            });
    }
}
