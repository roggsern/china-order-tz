<?php

namespace App\Services\Search;

use App\Models\Product;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\Inventory\CatalogStockPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Channel-scoped product search corpora for unified marketplace search.
 */
class ProductSearchCorpus
{
    public const MAX_MERGED_FETCH = 1000;

    public function __construct(
        private readonly ChinaSellableProductQuery $chinaSellable,
        private readonly TzStorefrontProductCorpus $tzCorpus,
        private readonly SearchRelevance $relevance,
    ) {}

    /**
     * @return Collection<int, Product>
     */
    public function searchChina(string $search, int $limit, string $sort = 'relevance'): Collection
    {
        return $this->buildChinaQuery($search, $sort)->limit($limit)->get();
    }

    /**
     * @return Collection<int, Product>
     */
    public function searchTz(string $search, int $limit, string $sort = 'relevance'): Collection
    {
        return $this->buildTzQuery($search, $sort)->limit($limit)->get();
    }

    /**
     * @return Builder<Product>
     */
    public function buildChinaQuery(string $search, string $sort = 'relevance'): Builder
    {
        $query = $this->baseEagerLoads($this->chinaBaseQuery());
        $this->relevance->applyProductMatchFilter($query, $search, ['include_store' => false]);
        $this->applySort($query, $search, $sort, includeStore: false);

        return $query;
    }

    /**
     * @return Builder<Product>
     */
    public function buildTzQuery(string $search, string $sort = 'relevance'): Builder
    {
        $query = $this->baseEagerLoads($this->tzBaseQuery());
        $this->relevance->applyProductMatchFilter($query, $search, ['include_store' => true]);
        $this->applySort($query, $search, $sort, includeStore: true);

        return $query;
    }

    public function countChina(string $search): int
    {
        $query = $this->chinaBaseQuery();
        $this->relevance->applyProductMatchFilter($query, $search, ['include_store' => false]);

        return (int) $query->count();
    }

    public function countTz(string $search): int
    {
        $query = $this->tzBaseQuery();
        $this->relevance->applyProductMatchFilter($query, $search, ['include_store' => true]);

        return (int) $query->count();
    }

    /**
     * @return Builder<Product>
     */
    private function chinaBaseQuery(): Builder
    {
        return $this->chinaSellable->apply(Product::query())->real()->whereNull('store_id');
    }

    /**
     * @return Builder<Product>
     */
    private function tzBaseQuery(): Builder
    {
        return $this->tzCorpus->apply(Product::query());
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySort(Builder $query, string $search, string $sort, bool $includeStore): void
    {
        if ($sort === 'newest') {
            $query->latest('products.created_at');

            return;
        }

        $this->relevance->applyProductRelevanceOrder($query, $search, ['include_store' => $includeStore]);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function baseEagerLoads(Builder $query): Builder
    {
        return $query
            ->with(array_merge([
                'commerceChannel:id,name,code',
                'category:id,name,slug,store_id',
                'brand:id,name,slug',
                'store:id,name,slug,code',
                'catalogProductType:id,name',
            ], CustomerProductMediaResolver::catalogEagerLoads(), CatalogStockPresenter::catalogListingEagerLoads()))
            ->withAvg(
                ['reviews as average_rating' => fn ($q) => $q->where('is_approved', true)],
                'rating',
            )
            ->withCount(
                ['reviews as review_count' => fn ($q) => $q->where('is_approved', true)],
            );
    }
}
