<?php

namespace App\Actions\CustomerCatalog;

use App\Enums\CommerceChannelCode;
use App\Models\Product;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\Inventory\CatalogStockPresenter;
use App\Services\Storefront\CatalogNavigationCrosswalkResolver;
use App\Support\Catalog\CatalogNavigationCrosswalk;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListProductsAction
{
    public function __construct(
        private readonly CatalogNavigationCrosswalkResolver $crosswalkResolver,
    ) {}

    public function handle(): LengthAwarePaginator
    {
        $search = trim((string) request()->query('search', ''));
        $category = request()->query('category');
        $brand = request()->query('brand');
        $store = request()->query('store');
        $channel = request()->query('commerce_channel');
        $origin = request()->query('origin');
        $featured = request()->query('featured');
        $perPage = min(max((int) request()->query('per_page', 15), 1), 48);

        if ($origin === 'tz' && ! filled($channel)) {
            $channel = CommerceChannelCode::TzLocal->value;
        }
        if ($origin === 'china' && ! filled($channel)) {
            $channel = CommerceChannelCode::ChinaImport->value;
        }

        return Product::query()
            ->real()
            ->purchasable()
            ->with(array_merge([
                'commerceChannel:id,name,code',
                'category:id,name,slug',
                'brand:id,name,slug',
                'store:id,name,slug',
            ], CustomerProductMediaResolver::catalogEagerLoads(), CatalogStockPresenter::catalogListingEagerLoads()))
            ->withAvg(
                ['reviews as average_rating' => fn ($query) => $query->where('is_approved', true)],
                'rating',
            )
            ->withCount(
                ['reviews as review_count' => fn ($query) => $query->where('is_approved', true)],
            )
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.mb_strtolower($search).'%';

                $query->where(function ($query) use ($term) {
                    $query->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(short_description) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
                });
            })
            ->when(filled($category), function ($query) use ($category) {
                if (CatalogNavigationCrosswalk::forBibleSlug($category) !== null) {
                    $categoryIds = $this->crosswalkResolver->categoryIdsForBibleSlug($category);

                    if ($categoryIds === []) {
                        $query->whereRaw('0 = 1');

                        return;
                    }

                    $query->whereIn('category_id', $categoryIds);

                    return;
                }

                $query->where(function ($query) use ($category) {
                    $query->where('category_id', $category)
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('slug', $category));
                });
            })
            ->when(filled($brand), function ($query) use ($brand) {
                $query->where(function ($query) use ($brand) {
                    $query->where('brand_id', $brand)
                        ->orWhereHas('brand', fn ($brandQuery) => $brandQuery->where('slug', $brand));
                });
            })
            ->when(filled($store), function ($query) use ($store) {
                $query->where(function ($query) use ($store) {
                    $query->where('store_id', $store)
                        ->orWhereHas('store', fn ($storeQuery) => $storeQuery->where('slug', $store));
                });
            })
            ->when(filled($channel), function ($query) use ($channel) {
                $query->whereHas('commerceChannel', fn ($q) => $q->where('code', $channel));
            })
            ->when(in_array($featured, ['1', 'true', 1, true], true), fn ($query) => $query->where('is_featured', true))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
