<?php

namespace App\Services\Storefront;

use App\Enums\CatalogOrigin;
use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
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
    /**
     * Navigation category tree for the ORDER FROM CHINA mega menu.
     *
     * @return Collection<int, Category>
     */
    public function navigationCategories(): Collection
    {
        // Catalog Bible roots only — excludes department flat roots, store categories,
        // and faker sample categories created by product factories.
        $bibleRootSlugs = collect(CatalogBible::categories())->pluck('slug')->all();
        $bibleChildSlugs = collect(CatalogBible::categories())
            ->flatMap(fn (array $root) => collect($root['children'] ?? [])->pluck('slug'))
            ->all();

        return Category::query()
            ->where('is_active', true)
            ->where('origin', CatalogOrigin::China)
            ->whereNull('store_id')
            ->whereNull('parent_id')
            ->whereIn('slug', $bibleRootSlugs)
            ->with([
                'children' => function ($q) use ($bibleChildSlugs) {
                    $q->where('is_active', true)
                        ->whereNull('store_id')
                        ->where('origin', CatalogOrigin::China)
                        ->where(function (Builder $child) use ($bibleChildSlugs) {
                            $child->whereIn('slug', $bibleChildSlugs)
                                ->orWhereHas('products', fn (Builder $p) => $this->chinaPublishedProductQuery($p));
                        })
                        ->orderBy('sort_order')
                        ->orderBy('name');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Brand>
     */
    public function brands(?string $categorySlug = null, int $limit = 24): Collection
    {
        $query = Brand::query()
            ->where('is_active', true)
            ->whereHas('products', fn (Builder $p) => $this->chinaPublishedProductQuery($p)
                ->when(filled($categorySlug), function (Builder $p) use ($categorySlug) {
                    $p->whereHas('category', function (Builder $c) use ($categorySlug) {
                        $c->whereNull('store_id')
                            ->where(function (Builder $inner) use ($categorySlug) {
                                $inner->where('slug', $categorySlug)
                                    ->orWhereHas('parent', fn (Builder $parent) => $parent->where('slug', $categorySlug))
                                    ->orWhereHas('parent.parent', fn (Builder $grand) => $grand->where('slug', $categorySlug));
                            });
                    });
                }))
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

        return $this->chinaPublishedProductQuery(Product::query())
            ->real()
            ->whereNull('store_id')
            ->with([
                'commerceChannel:id,name,code',
                'category:id,name,slug',
                'brand:id,name,slug',
                'images' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->withAvg(
                ['reviews as average_rating' => fn ($query) => $query->where('is_approved', true)],
                'rating',
            )
            ->withCount(
                ['reviews as review_count' => fn ($query) => $query->where('is_approved', true)],
            )
            ->when(filled($category), function (Builder $query) use ($category) {
                $query->where(function (Builder $q) use ($category) {
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
            })
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

    private function chinaPublishedProductQuery(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_demo', false)
            ->where('lifecycle_status', ProductLifecycleStatus::Active)
            ->where('visibility', ProductVisibility::Public)
            ->whereNull('store_id')
            ->whereHas('commerceChannel', fn (Builder $q) => $q->where('code', CommerceChannelCode::ChinaImport->value));
    }
}
