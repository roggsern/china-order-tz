<?php

namespace App\Services\Storefront;

use App\Enums\CommerceChannelCode;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\Inventory\CatalogStockPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * BUY FROM TZ marketplace catalog — Store-scoped, TZ_LOCAL only.
 * Does not mix China import brands or catalog.
 */
class TzStorefrontCatalog
{
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
     * @return Collection<int, Category>
     */
    public function categories(Store $store): Collection
    {
        return Category::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function products(Store $store, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 48);
        $category = $filters['category'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        return $this->storeProductQuery($store)
            ->with(array_merge([
                'commerceChannel:id,name,code',
                'category:id,name,slug,store_id',
                'brand:id,name,slug',
                'store:id,name,slug,code',
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
                        ->orWhereRaw('LOWER(short_description) LIKE ?', [$term]);
                });
            })
            ->when(filled($category), function (Builder $query) use ($category, $store) {
                $query->where(function (Builder $q) use ($category, $store) {
                    $q->where('category_id', $category)
                        ->orWhereHas('category', function (Builder $cq) use ($category, $store) {
                            $cq->where('store_id', $store->id)
                                ->where(function (Builder $inner) use ($category) {
                                    $inner->where('slug', $category)->orWhere('id', $category);
                                });
                        });
                });
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

    private function storeProductQuery(Store $store): Builder
    {
        $warehouseCode = strtoupper((string) $store->code);

        return Product::query()
            ->real()
            ->published()
            ->where('store_id', $store->id)
            ->whereHas('commerceChannel', fn (Builder $q) => $q->where('code', CommerceChannelCode::TzLocal->value))
            ->where(function (Builder $q) use ($warehouseCode, $store) {
                // StockResolver-aligned visibility: active store-location available > 0, or no inventory rows yet.
                $q->whereHas('variants', function (Builder $vq) use ($warehouseCode, $store) {
                    $vq->where('is_active', true)
                        ->where(function (Builder $inv) use ($warehouseCode, $store) {
                            $inv->whereDoesntHave('inventories')
                                ->orWhereHas('inventories', function (Builder $iq) use ($warehouseCode, $store) {
                                    $iq->where('is_active', true)
                                        ->where(function (Builder $loc) use ($warehouseCode, $store) {
                                            $loc->where('warehouse_code', $warehouseCode)
                                                ->orWhereHas('inventoryLocation', fn (Builder $lq) => $lq
                                                    ->where('store_id', $store->id)
                                                    ->where('is_default', true));
                                        })
                                        ->whereRaw('(on_hand - reserved) > 0');
                                });
                        });
                })->orWhereDoesntHave('variants');
            });
    }
}
