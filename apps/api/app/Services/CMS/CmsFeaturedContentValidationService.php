<?php

namespace App\Services\CMS;

use App\Enums\CatalogOrigin;
use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsFeaturedItemType;
use App\Enums\CMS\CmsFeaturedSourceType;
use App\Enums\CMS\CmsHomepageSectionType;
use App\Enums\CommerceChannelCode;
use App\Enums\PromotionStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Store;
use Illuminate\Validation\ValidationException;

/**
 * Validates featured-content source configuration against commerce engines.
 */
class CmsFeaturedContentValidationService
{
    public function assertSectionAllowsFeatured(CmsHomepageSectionType $type): void
    {
        if ($type === CmsHomepageSectionType::Hero) {
            throw ValidationException::withMessages([
                'section' => ['HERO sections cannot own featured content. Use hero slides instead.'],
            ]);
        }

        if (! $type->supportsFeaturedContent()) {
            throw ValidationException::withMessages([
                'section' => ['This section type does not support featured content.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function assertConfiguration(
        CmsFeaturedSourceType $sourceType,
        array $configuration,
        CmsCommerceContext $layoutContext,
        CmsHomepageSectionType $sectionType,
    ): void {
        match ($sourceType) {
            CmsFeaturedSourceType::Manual => $this->assertManual($configuration, $layoutContext, $sectionType),
            CmsFeaturedSourceType::BestSellers,
            CmsFeaturedSourceType::NewArrivals,
            CmsFeaturedSourceType::MostViewed => $this->assertChannelAwareCatalogSource($layoutContext),
            CmsFeaturedSourceType::Promotion => $this->assertPromotionSource($configuration),
            CmsFeaturedSourceType::Category => $this->assertCategorySource($configuration, $layoutContext),
            CmsFeaturedSourceType::Brand => $this->assertBrandSource($configuration, $layoutContext),
            CmsFeaturedSourceType::Store => $this->assertStoreSource($configuration, $layoutContext),
            CmsFeaturedSourceType::Collection => $this->assertCollectionSource($configuration, $layoutContext),
            CmsFeaturedSourceType::SearchFilter => $this->assertSearchFilter($configuration, $layoutContext),
        };
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function assertManual(
        array $configuration,
        CmsCommerceContext $layoutContext,
        CmsHomepageSectionType $sectionType,
    ): void {
        $itemType = CmsFeaturedItemType::tryFrom((string) ($configuration['item_type'] ?? 'PRODUCT'))
            ?? CmsFeaturedItemType::Product;
        $ids = $configuration['item_ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            throw ValidationException::withMessages([
                'configuration.item_ids' => ['Manual source requires a non-empty item_ids array.'],
            ]);
        }

        $ids = array_values(array_unique(array_map('strval', $ids)));

        match ($itemType) {
            CmsFeaturedItemType::Product => $this->assertProducts($ids, $layoutContext),
            CmsFeaturedItemType::Store => $this->assertStores($ids, $layoutContext),
            CmsFeaturedItemType::Brand => $this->assertBrands($ids, $layoutContext),
            CmsFeaturedItemType::Category => $this->assertCategories($ids, $layoutContext),
        };

        // Section type hints — soft consistency.
        if ($sectionType === CmsHomepageSectionType::ShopByStore && $itemType !== CmsFeaturedItemType::Store) {
            throw ValidationException::withMessages([
                'configuration.item_type' => ['SHOP_BY_STORE sections require STORE item_type for MANUAL source.'],
            ]);
        }
        if ($sectionType === CmsHomepageSectionType::FeaturedBrands && $itemType !== CmsFeaturedItemType::Brand) {
            throw ValidationException::withMessages([
                'configuration.item_type' => ['FEATURED_BRANDS sections require BRAND item_type for MANUAL source.'],
            ]);
        }
        if ($sectionType === CmsHomepageSectionType::FeaturedCategories && $itemType !== CmsFeaturedItemType::Category) {
            throw ValidationException::withMessages([
                'configuration.item_type' => ['FEATURED_CATEGORIES sections require CATEGORY item_type for MANUAL source.'],
            ]);
        }
    }

    private function assertChannelAwareCatalogSource(CmsCommerceContext $layoutContext): void
    {
        if ($layoutContext === CmsCommerceContext::Global) {
            throw ValidationException::withMessages([
                'source_type' => [
                    'GLOBAL layouts cannot use channel-ranked product sources. Use MANUAL with safe shared targets or a channel layout.',
                ],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function assertPromotionSource(array $configuration): void
    {
        $id = (string) ($configuration['promotion_id'] ?? '');
        if ($id === '') {
            throw ValidationException::withMessages([
                'configuration.promotion_id' => ['promotion_id is required for PROMOTION source.'],
            ]);
        }

        $promotion = Promotion::query()->find($id);
        if ($promotion === null) {
            throw ValidationException::withMessages([
                'configuration.promotion_id' => ['Referenced promotion does not exist.'],
            ]);
        }

        if ($promotion->status === PromotionStatus::Expired) {
            throw ValidationException::withMessages([
                'configuration.promotion_id' => ['Referenced promotion is expired.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function assertCategorySource(array $configuration, CmsCommerceContext $layoutContext): void
    {
        $id = (string) ($configuration['category_id'] ?? '');
        if ($id === '') {
            throw ValidationException::withMessages([
                'configuration.category_id' => ['category_id is required for CATEGORY source.'],
            ]);
        }
        $this->assertCategories([$id], $layoutContext);
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function assertBrandSource(array $configuration, CmsCommerceContext $layoutContext): void
    {
        $id = (string) ($configuration['brand_id'] ?? '');
        if ($id === '') {
            throw ValidationException::withMessages([
                'configuration.brand_id' => ['brand_id is required for BRAND source.'],
            ]);
        }
        $this->assertBrands([$id], $layoutContext);
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function assertStoreSource(array $configuration, CmsCommerceContext $layoutContext): void
    {
        $id = (string) ($configuration['store_id'] ?? '');
        if ($id === '') {
            throw ValidationException::withMessages([
                'configuration.store_id' => ['store_id is required for STORE source.'],
            ]);
        }
        $this->assertStores([$id], $layoutContext);
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function assertCollectionSource(array $configuration, CmsCommerceContext $layoutContext): void
    {
        // No Collection engine yet — curated category ID lists.
        $ids = $configuration['category_ids'] ?? $configuration['item_ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            throw ValidationException::withMessages([
                'configuration.category_ids' => [
                    'COLLECTION source requires category_ids (curated category references) until a Collection engine exists.',
                ],
            ]);
        }
        $this->assertCategories(array_map('strval', $ids), $layoutContext);
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function assertSearchFilter(array $configuration, CmsCommerceContext $layoutContext): void
    {
        if ($layoutContext === CmsCommerceContext::Global) {
            throw ValidationException::withMessages([
                'source_type' => ['GLOBAL layouts cannot use SEARCH_FILTER product queries.'],
            ]);
        }

        if (! isset($configuration['filters']) || ! is_array($configuration['filters'])) {
            throw ValidationException::withMessages([
                'configuration.filters' => ['SEARCH_FILTER source requires a filters object.'],
            ]);
        }
    }

    /**
     * @param  list<string>  $ids
     */
    private function assertProducts(array $ids, CmsCommerceContext $layoutContext): void
    {
        if ($layoutContext === CmsCommerceContext::Global) {
            throw ValidationException::withMessages([
                'configuration.item_ids' => [
                    'GLOBAL featured content cannot reference channel products. Use a channel layout.',
                ],
            ]);
        }

        $expected = $layoutContext->toCommerceChannelCode();
        foreach ($ids as $id) {
            $product = Product::query()->with('commerceChannel')->find($id);
            if ($product === null) {
                throw ValidationException::withMessages([
                    'configuration.item_ids' => ["Product {$id} does not exist."],
                ]);
            }
            $code = $product->commerceChannel?->code;
            $channel = $code instanceof CommerceChannelCode
                ? $code
                : CommerceChannelCode::tryFrom((string) $code);
            if ($expected !== null && $channel !== $expected) {
                throw ValidationException::withMessages([
                    'configuration.item_ids' => [
                        sprintf('Product %s does not belong to %s.', $id, $layoutContext->value),
                    ],
                ]);
            }
        }
    }

    /**
     * @param  list<string>  $ids
     */
    private function assertStores(array $ids, CmsCommerceContext $layoutContext): void
    {
        if ($layoutContext === CmsCommerceContext::ChinaImport) {
            throw ValidationException::withMessages([
                'configuration.item_ids' => ['CHINA_IMPORT content cannot reference Tanzanian stores.'],
            ]);
        }
        if ($layoutContext === CmsCommerceContext::Global) {
            throw ValidationException::withMessages([
                'configuration.item_ids' => ['GLOBAL featured content cannot reference stores.'],
            ]);
        }

        foreach ($ids as $id) {
            $store = Store::query()->find($id);
            if ($store === null) {
                throw ValidationException::withMessages([
                    'configuration.item_ids' => ["Store {$id} does not exist."],
                ]);
            }
        }
    }

    /**
     * @param  list<string>  $ids
     */
    private function assertBrands(array $ids, CmsCommerceContext $layoutContext): void
    {
        if ($layoutContext === CmsCommerceContext::Global) {
            throw ValidationException::withMessages([
                'configuration.item_ids' => ['GLOBAL featured content cannot reference brands without a channel.'],
            ]);
        }

        $channel = $layoutContext->toCommerceChannelCode();
        foreach ($ids as $id) {
            $brand = Brand::query()->find($id);
            if ($brand === null) {
                throw ValidationException::withMessages([
                    'configuration.item_ids' => ["Brand {$id} does not exist."],
                ]);
            }
            if ($channel !== null) {
                $has = Product::query()
                    ->where('brand_id', $brand->id)
                    ->whereHas('commerceChannel', fn ($q) => $q->where('code', $channel->value))
                    ->exists();
                if (! $has) {
                    throw ValidationException::withMessages([
                        'configuration.item_ids' => [
                            sprintf('Brand %s has no products on %s.', $id, $layoutContext->value),
                        ],
                    ]);
                }
            }
        }
    }

    /**
     * @param  list<string>  $ids
     */
    private function assertCategories(array $ids, CmsCommerceContext $layoutContext): void
    {
        if ($layoutContext === CmsCommerceContext::Global) {
            throw ValidationException::withMessages([
                'configuration.item_ids' => ['GLOBAL featured content cannot reference catalog categories.'],
            ]);
        }

        $expectedOrigin = match ($layoutContext) {
            CmsCommerceContext::ChinaImport => CatalogOrigin::China,
            CmsCommerceContext::TzLocal => CatalogOrigin::Tz,
            default => null,
        };

        foreach ($ids as $id) {
            $category = Category::query()->find($id);
            if ($category === null) {
                throw ValidationException::withMessages([
                    'configuration.item_ids' => ["Category {$id} does not exist."],
                ]);
            }
            $origin = $category->resolvedOrigin();
            if ($expectedOrigin !== null && $origin !== null && $origin !== $expectedOrigin) {
                throw ValidationException::withMessages([
                    'configuration.item_ids' => [
                        sprintf('Category %s origin does not match %s.', $id, $layoutContext->value),
                    ],
                ]);
            }
        }
    }
}
