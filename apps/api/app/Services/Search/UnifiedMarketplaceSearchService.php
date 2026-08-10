<?php

namespace App\Services\Search;

use App\Enums\CommerceChannelCode;
use App\Http\Resources\CustomerProductCardResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;

class UnifiedMarketplaceSearchService
{
    public function __construct(
        private readonly ProductSearchCorpus $productCorpus,
        private readonly SearchRelevance $relevance,
    ) {}

    /**
     * @param  array{
     *   limit_products?: int,
     *   limit_brands?: int,
     *   limit_stores?: int,
     *   limit_categories?: int
     * }  $limits
     * @return array{
     *   q: string,
     *   scope: string,
     *   products: list<array<string, mixed>>,
     *   brands: list<array<string, mixed>>,
     *   stores: list<array<string, mixed>>,
     *   categories: list<array<string, mixed>>
     * }
     */
    public function suggest(string $q, string $scope = 'all', array $limits = []): array
    {
        $q = trim($q);
        $scope = $this->normalizeScope($scope);
        $limitProducts = $this->clamp((int) ($limits['limit_products'] ?? 8), 1, 24);
        $limitBrands = $this->clamp((int) ($limits['limit_brands'] ?? 4), 0, 8);
        $limitStores = $this->clamp((int) ($limits['limit_stores'] ?? 4), 0, 8);
        $limitCategories = $this->clamp((int) ($limits['limit_categories'] ?? 4), 0, 8);

        if ($q === '') {
            return [
                'q' => '',
                'scope' => $scope,
                'products' => [],
                'brands' => [],
                'stores' => [],
                'categories' => [],
            ];
        }

        $perChannel = max($limitProducts, 8);

        $products = collect();
        if ($scope === 'all' || $scope === 'china') {
            $products = $products->merge($this->productCorpus->searchChina($q, $perChannel));
        }
        if ($scope === 'all' || $scope === 'tz') {
            $products = $products->merge($this->productCorpus->searchTz($q, $perChannel));
        }

        $productPayload = $products
            ->unique('id')
            ->map(fn (Product $product) => $this->mapProduct($product, $q))
            ->sortByDesc(fn (array $row) => [$row['relevance_score'], $row['slug']])
            ->values()
            ->take($limitProducts)
            ->all();

        $brands = ($scope === 'tz')
            ? []
            : $this->searchBrands($q, $limitBrands, $scope);

        $stores = ($scope === 'china')
            ? []
            : $this->searchStores($q, $limitStores);

        $categories = $this->searchCategories($q, $limitCategories, $scope);

        return [
            'q' => $q,
            'scope' => $scope,
            'products' => $productPayload,
            'brands' => $brands,
            'stores' => $stores,
            'categories' => $categories,
        ];
    }

    /**
     * Paginated product results for the unified search results page.
     *
     * @return array{
     *   data: list<array<string, mixed>>,
     *   meta: array{
     *     current_page: int,
     *     last_page: int,
     *     per_page: int,
     *     total: int,
     *     q: string,
     *     scope: string
     *   }
     * }
     */
    public function products(
        string $q,
        string $scope = 'all',
        int $page = 1,
        int $perPage = 24,
        string $sort = 'relevance',
    ): array {
        $q = trim($q);
        $scope = $this->normalizeScope($scope);
        $sort = $this->normalizeSort($sort);
        $page = max(1, $page);
        $perPage = $this->clamp($perPage, 1, 48);

        if ($q === '') {
            return [
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'q' => '',
                    'scope' => $scope,
                ],
            ];
        }

        if ($scope === 'china' || $scope === 'tz') {
            return $this->paginateSingleScope($q, $scope, $page, $perPage, $sort);
        }

        return $this->paginateMergedScope($q, $page, $perPage, $sort);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function paginateSingleScope(
        string $q,
        string $scope,
        int $page,
        int $perPage,
        string $sort,
    ): array {
        $query = $scope === 'china'
            ? $this->productCorpus->buildChinaQuery($q, $sort)
            : $this->productCorpus->buildTzQuery($q, $sort);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $data = collect($paginator->items())
            ->map(fn (Product $product) => $this->mapProduct($product, $q))
            ->values()
            ->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => max(1, $paginator->lastPage()),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'q' => $q,
                'scope' => $scope,
            ],
        ];
    }

    /**
     * Merge China + TZ ranked hits, then slice for the requested page.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function paginateMergedScope(string $q, int $page, int $perPage, string $sort): array
    {
        $total = $this->productCorpus->countChina($q) + $this->productCorpus->countTz($q);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        // Pull enough from each channel to fill the merged page window.
        $channelLimit = min(
            ProductSearchCorpus::MAX_MERGED_FETCH,
            max($page * $perPage, $total, $perPage),
        );

        $merged = collect()
            ->merge($this->productCorpus->searchChina($q, $channelLimit, $sort))
            ->merge($this->productCorpus->searchTz($q, $channelLimit, $sort))
            ->unique('id');

        if ($sort === 'newest') {
            $merged = $merged->sortByDesc(
                fn (Product $product) => $product->created_at?->getTimestamp() ?? 0,
            );
        } else {
            $merged = $merged->sortByDesc(fn (Product $product) => [
                $this->relevance->score($product, $q),
                $product->slug,
            ]);
        }

        $data = $merged
            ->values()
            ->slice(($page - 1) * $perPage, $perPage)
            ->map(fn (Product $product) => $this->mapProduct($product, $q))
            ->values()
            ->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'q' => $q,
                'scope' => 'all',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapProduct(Product $product, string $q): array
    {
        $channelCode = $product->commerceChannel?->code;
        $marketplace = $channelCode === CommerceChannelCode::TzLocal->value ? 'tz' : 'china';

        $card = (new CustomerProductCardResource($product))->toArray(Request::create('/'));

        return [
            ...$card,
            'marketplace' => $marketplace,
            'commerce_channel_code' => $channelCode,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'slug' => $product->brand->slug,
                'name' => $product->brand->name,
            ] : null,
            'store' => $product->store ? [
                'id' => $product->store->id,
                'slug' => $product->store->slug,
                'name' => $product->store->name,
            ] : null,
            'matched_on' => $this->relevance->matchedOn($product, $q),
            'relevance_score' => $this->relevance->score($product, $q),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchBrands(string $q, int $limit, string $scope): array
    {
        if ($limit === 0) {
            return [];
        }

        $term = '%'.$this->relevance->normalize($q).'%';

        $query = Brand::query()
            ->where('is_active', true)
            ->where(function ($inner) use ($term) {
                $inner->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$term]);
            });

        if ($scope === 'china') {
            $china = app(ChinaSellableProductQuery::class);
            $query->whereHas('products', fn ($p) => $china->apply($p)->real()->whereNull('store_id'));
        }

        return $query
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->limit(max($limit * 3, $limit))
            ->get(['id', 'name', 'slug', 'logo', 'is_featured'])
            ->map(function (Brand $brand) use ($q) {
                return [
                    'kind' => 'catalog_brand',
                    'id' => $brand->id,
                    'slug' => $brand->slug,
                    'name' => $brand->name,
                    'logo' => $brand->logo,
                    'relevance_score' => $this->relevance->entityScore($brand->name, $brand->slug, $q),
                ];
            })
            ->filter(fn (array $row) => $row['relevance_score'] > 0)
            ->sortByDesc('relevance_score')
            ->values()
            ->take($limit)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchStores(string $q, int $limit): array
    {
        if ($limit === 0) {
            return [];
        }

        $term = '%'.$this->relevance->normalize($q).'%';

        return Store::query()
            ->storefrontVisible()
            ->where(function ($inner) use ($term) {
                $inner->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$term]);
            })
            ->orderBy('name')
            ->limit(max($limit * 3, $limit))
            ->get(['id', 'name', 'slug', 'code', 'logo_path'])
            ->map(function (Store $store) use ($q) {
                return [
                    'kind' => 'tz_store',
                    'id' => $store->id,
                    'slug' => $store->slug,
                    'name' => $store->name,
                    'code' => $store->code,
                    'relevance_score' => $this->relevance->entityScore($store->name, $store->slug, $q),
                ];
            })
            ->filter(fn (array $row) => $row['relevance_score'] > 0)
            ->sortByDesc('relevance_score')
            ->values()
            ->take($limit)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchCategories(string $q, int $limit, string $scope): array
    {
        if ($limit === 0) {
            return [];
        }

        $term = '%'.$this->relevance->normalize($q).'%';

        $query = Category::query()
            ->where('is_active', true)
            ->where(function ($inner) use ($term) {
                $inner->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$term]);
            });

        if ($scope === 'china') {
            $query->whereNull('store_id');
        } elseif ($scope === 'tz') {
            $query->whereNotNull('store_id');
        }

        return $query
            ->orderBy('name')
            ->limit(max($limit * 3, $limit))
            ->get(['id', 'name', 'slug', 'store_id', 'origin'])
            ->map(function (Category $category) use ($q) {
                return [
                    'kind' => 'category',
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'store_id' => $category->store_id,
                    'relevance_score' => $this->relevance->entityScore($category->name, $category->slug, $q),
                ];
            })
            ->filter(fn (array $row) => $row['relevance_score'] > 0)
            ->sortByDesc('relevance_score')
            ->values()
            ->take($limit)
            ->all();
    }

    private function normalizeScope(string $scope): string
    {
        $scope = strtolower(trim($scope));

        return in_array($scope, ['all', 'china', 'tz'], true) ? $scope : 'all';
    }

    private function normalizeSort(string $sort): string
    {
        $sort = strtolower(trim($sort));

        return in_array($sort, ['relevance', 'newest'], true) ? $sort : 'relevance';
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
