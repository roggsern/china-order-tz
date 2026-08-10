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
    public function __construct(
        private readonly ChinaSellableProductQuery $chinaSellable,
        private readonly TzStorefrontProductCorpus $tzCorpus,
        private readonly SearchRelevance $relevance,
    ) {}

    /**
     * @return Collection<int, Product>
     */
    public function searchChina(string $search, int $limit): Collection
    {
        $query = $this->baseEagerLoads(
            $this->chinaSellable->apply(Product::query())->real()->whereNull('store_id'),
        );
        $this->relevance->applyProductMatchFilter($query, $search, ['include_store' => false]);
        $this->relevance->applyProductRelevanceOrder($query, $search, ['include_store' => false]);

        return $query->limit($limit)->get();
    }

    /**
     * @return Collection<int, Product>
     */
    public function searchTz(string $search, int $limit): Collection
    {
        $query = $this->baseEagerLoads($this->tzCorpus->apply(Product::query()));
        $this->relevance->applyProductMatchFilter($query, $search, ['include_store' => true]);
        $this->relevance->applyProductRelevanceOrder($query, $search, ['include_store' => true]);

        return $query->limit($limit)->get();
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
