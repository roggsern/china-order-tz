<?php

namespace App\Services\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsFeaturedItemType;
use App\Enums\CMS\CmsFeaturedSourceType;
use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\PromotionRuleType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsFeaturedContent;
use App\Models\CmsHomepageLayout;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves featured content to live commerce entities (no data duplication).
 *
 * @phpstan-type ResolvedItem array{item_type: string, id: string, entity: mixed}
 */
class CmsFeaturedContentResolver
{
    /**
     * @return list<ResolvedItem>
     */
    public function resolve(CmsFeaturedContent $featured, CmsHomepageLayout $layout): array
    {
        $context = $layout->commerce_context;
        $limit = max(1, min(48, (int) $featured->limit));
        $config = $featured->configuration ?? [];

        return match ($featured->source_type) {
            CmsFeaturedSourceType::Manual => $this->resolveManual($config, $context, $limit),
            CmsFeaturedSourceType::BestSellers => $this->mapProducts(
                $this->bestSellers($context, $limit),
            ),
            CmsFeaturedSourceType::NewArrivals => $this->mapProducts(
                $this->baseProductQuery($context)->latest('created_at')->limit($limit)->get(),
            ),
            CmsFeaturedSourceType::MostViewed => $this->mapProducts(
                // No view analytics yet — featured + recent as documented heuristic.
                $this->baseProductQuery($context)
                    ->where('is_featured', true)
                    ->latest('created_at')
                    ->limit($limit)
                    ->get(),
            ),
            CmsFeaturedSourceType::Promotion => $this->resolvePromotion($config, $context, $limit),
            CmsFeaturedSourceType::Category => $this->resolveCategoryProducts($config, $context, $limit),
            CmsFeaturedSourceType::Brand => $this->resolveBrandProducts($config, $context, $limit),
            CmsFeaturedSourceType::Store => $this->resolveStoreProducts($config, $context, $limit),
            CmsFeaturedSourceType::Collection => $this->resolveCollection($config, $context, $limit),
            CmsFeaturedSourceType::SearchFilter => $this->resolveSearchFilter($config, $context, $limit),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<ResolvedItem>
     */
    private function resolveManual(array $config, CmsCommerceContext $context, int $limit): array
    {
        $itemType = CmsFeaturedItemType::tryFrom((string) ($config['item_type'] ?? 'PRODUCT'))
            ?? CmsFeaturedItemType::Product;
        $ids = array_values(array_unique(array_map('strval', $config['item_ids'] ?? [])));
        $ids = array_slice($ids, 0, $limit);

        return match ($itemType) {
            CmsFeaturedItemType::Product => $this->mapProducts(
                $this->baseProductQuery($context)->whereIn('id', $ids)->get()->sortBy(
                    fn (Product $p) => array_search($p->id, $ids, true),
                )->values(),
            ),
            CmsFeaturedItemType::Store => $this->mapStores(
                Store::query()->whereIn('id', $ids)->where('is_active', true)->get()->sortBy(
                    fn (Store $s) => array_search($s->id, $ids, true),
                )->values(),
            ),
            CmsFeaturedItemType::Brand => $this->mapBrands(
                Brand::query()->whereIn('id', $ids)->where('is_active', true)->get()->sortBy(
                    fn (Brand $b) => array_search($b->id, $ids, true),
                )->values(),
            ),
            CmsFeaturedItemType::Category => $this->mapCategories(
                Category::query()->whereIn('id', $ids)->where('is_active', true)->get()->sortBy(
                    fn (Category $c) => array_search($c->id, $ids, true),
                )->values(),
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<ResolvedItem>
     */
    private function resolvePromotion(array $config, CmsCommerceContext $context, int $limit): array
    {
        $promotion = Promotion::query()
            ->with('rules')
            ->find((string) ($config['promotion_id'] ?? ''));
        if ($promotion === null) {
            return [];
        }

        $productIds = [];
        $categoryIds = [];
        foreach ($promotion->rules as $rule) {
            $value = $rule->rule_value;
            if (! is_array($value)) {
                continue;
            }
            $ids = [];
            if (isset($value['ids']) && is_array($value['ids'])) {
                $ids = array_map('strval', $value['ids']);
            } elseif (isset($value['id'])) {
                $ids = [(string) $value['id']];
            } elseif (isset($value['product_id'])) {
                $ids = [(string) $value['product_id']];
            } elseif (array_is_list($value)) {
                $ids = array_map('strval', $value);
            }
            if ($rule->rule_type === PromotionRuleType::Product) {
                $productIds = array_merge($productIds, $ids);
            }
            if ($rule->rule_type === PromotionRuleType::Category) {
                $categoryIds = array_merge($categoryIds, $ids);
            }
        }

        $query = $this->baseProductQuery($context);
        if ($productIds !== []) {
            $query->whereIn('id', array_unique($productIds));
        } elseif ($categoryIds !== []) {
            $query->whereIn('category_id', array_unique($categoryIds));
        } else {
            return [];
        }

        return $this->mapProducts($query->limit($limit)->get());
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<ResolvedItem>
     */
    private function resolveCategoryProducts(array $config, CmsCommerceContext $context, int $limit): array
    {
        $categoryId = (string) ($config['category_id'] ?? '');
        if ($categoryId === '') {
            return [];
        }

        return $this->mapProducts(
            $this->baseProductQuery($context)
                ->where('category_id', $categoryId)
                ->latest('created_at')
                ->limit($limit)
                ->get(),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<ResolvedItem>
     */
    private function resolveBrandProducts(array $config, CmsCommerceContext $context, int $limit): array
    {
        $brandId = (string) ($config['brand_id'] ?? '');
        if ($brandId === '') {
            return [];
        }

        return $this->mapProducts(
            $this->baseProductQuery($context)
                ->where('brand_id', $brandId)
                ->latest('created_at')
                ->limit($limit)
                ->get(),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<ResolvedItem>
     */
    private function resolveStoreProducts(array $config, CmsCommerceContext $context, int $limit): array
    {
        if ($context !== CmsCommerceContext::TzLocal) {
            return [];
        }
        $storeId = (string) ($config['store_id'] ?? '');
        if ($storeId === '') {
            return [];
        }

        return $this->mapProducts(
            $this->baseProductQuery($context)
                ->where('store_id', $storeId)
                ->latest('created_at')
                ->limit($limit)
                ->get(),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<ResolvedItem>
     */
    private function resolveCollection(array $config, CmsCommerceContext $context, int $limit): array
    {
        $ids = array_map('strval', $config['category_ids'] ?? $config['item_ids'] ?? []);
        if ($ids === []) {
            return [];
        }

        return $this->mapCategories(
            Category::query()
                ->whereIn('id', $ids)
                ->where('is_active', true)
                ->limit($limit)
                ->get()
                ->sortBy(fn (Category $c) => array_search($c->id, $ids, true))
                ->values(),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<ResolvedItem>
     */
    private function resolveSearchFilter(array $config, CmsCommerceContext $context, int $limit): array
    {
        $filters = $config['filters'] ?? [];
        if (! is_array($filters)) {
            return [];
        }

        $query = $this->baseProductQuery($context);
        if (! empty($filters['category_id'])) {
            $query->where('category_id', (string) $filters['category_id']);
        }
        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', (string) $filters['brand_id']);
        }
        if (! empty($filters['store_id'])) {
            $query->where('store_id', (string) $filters['store_id']);
        }
        if (! empty($filters['is_featured'])) {
            $query->where('is_featured', true);
        }
        if (isset($filters['min_price'])) {
            $query->where('price', '>=', (float) $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $query->where('price', '<=', (float) $filters['max_price']);
        }

        return $this->mapProducts($query->latest('created_at')->limit($limit)->get());
    }

    /**
     * @return Collection<int, Product>
     */
    private function bestSellers(CmsCommerceContext $context, int $limit): Collection
    {
        $base = $this->baseProductQuery($context);

        if (Schema::hasTable('order_items')) {
            $ranked = OrderItem::query()
                ->selectRaw('product_id, SUM(quantity) as sold_qty')
                ->whereNotNull('product_id')
                ->groupBy('product_id')
                ->orderByDesc('sold_qty')
                ->limit($limit * 3)
                ->pluck('product_id');

            if ($ranked->isNotEmpty()) {
                $products = $base->whereIn('id', $ranked)->get()
                    ->sortBy(fn (Product $p) => $ranked->search($p->id))
                    ->values()
                    ->take($limit);

                if ($products->isNotEmpty()) {
                    return $products;
                }
            }
        }

        // Fallback heuristic when sales history is empty.
        return $base->where('is_featured', true)->orderBy('sort_order')->latest('created_at')->limit($limit)->get();
    }

    /**
     * @return Builder<Product>
     */
    private function baseProductQuery(CmsCommerceContext $context)
    {
        $query = Product::query()
            ->where('is_active', true)
            ->where('lifecycle_status', ProductLifecycleStatus::Active->value)
            ->with(['brand', 'category', 'commerceChannel', 'images']);

        $channel = $context->toCommerceChannelCode();
        if ($channel instanceof CommerceChannelCode) {
            $query->whereHas('commerceChannel', fn ($q) => $q->where('code', $channel->value));
        }

        return $query;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<ResolvedItem>
     */
    private function mapProducts(Collection $products): array
    {
        return $products->map(fn (Product $p) => [
            'item_type' => CmsFeaturedItemType::Product->value,
            'id' => $p->id,
            'entity' => $p,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Store>  $stores
     * @return list<ResolvedItem>
     */
    private function mapStores(Collection $stores): array
    {
        return $stores->map(fn (Store $s) => [
            'item_type' => CmsFeaturedItemType::Store->value,
            'id' => $s->id,
            'entity' => $s,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Brand>  $brands
     * @return list<ResolvedItem>
     */
    private function mapBrands(Collection $brands): array
    {
        return $brands->map(fn (Brand $b) => [
            'item_type' => CmsFeaturedItemType::Brand->value,
            'id' => $b->id,
            'entity' => $b,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return list<ResolvedItem>
     */
    private function mapCategories(Collection $categories): array
    {
        return $categories->map(fn (Category $c) => [
            'item_type' => CmsFeaturedItemType::Category->value,
            'id' => $c->id,
            'entity' => $c,
        ])->values()->all();
    }
}
