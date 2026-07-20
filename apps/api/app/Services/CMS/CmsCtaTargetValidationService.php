<?php

namespace App\Services\CMS;

use App\Enums\CatalogOrigin;
use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CommerceChannelCode;
use App\Enums\PromotionStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Store;
use Illuminate\Validation\ValidationException;

/**
 * Validates hero CTA targets against existing platform entities and commerce context.
 */
class CmsCtaTargetValidationService
{
    public function assertCta(
        string $fieldPrefix,
        ?CmsCtaTargetType $type,
        ?string $value,
        ?string $label,
        CmsCommerceContext $layoutContext,
    ): void {
        if ($type === null || $type === CmsCtaTargetType::None) {
            if ($value !== null && $value !== '') {
                throw ValidationException::withMessages([
                    $fieldPrefix.'_value' => ['CTA value must be empty when type is NONE.'],
                ]);
            }

            return;
        }

        if ($type->requiresValue() && ($value === null || $value === '')) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => ['CTA value is required for type '.$type->value.'.'],
            ]);
        }

        if ($label === null || $label === '') {
            throw ValidationException::withMessages([
                $fieldPrefix.'_label' => ['CTA label is required when a CTA type is set.'],
            ]);
        }

        match ($type) {
            CmsCtaTargetType::Url => $this->assertSafeUrl($fieldPrefix, (string) $value),
            CmsCtaTargetType::Product => $this->assertProduct($fieldPrefix, (string) $value, $layoutContext),
            CmsCtaTargetType::Store => $this->assertStore($fieldPrefix, (string) $value, $layoutContext),
            CmsCtaTargetType::Category => $this->assertCategory($fieldPrefix, (string) $value, $layoutContext),
            CmsCtaTargetType::Brand => $this->assertBrand($fieldPrefix, (string) $value, $layoutContext),
            CmsCtaTargetType::Promotion => $this->assertPromotion($fieldPrefix, (string) $value, $layoutContext),
            CmsCtaTargetType::Page => $this->assertPage($fieldPrefix, (string) $value, $layoutContext),
            CmsCtaTargetType::ChinaOrderForm => $this->assertChinaOrderForm($fieldPrefix, $layoutContext),
            CmsCtaTargetType::None => null,
        };
    }

    /**
     * Storefront-safe CTA payload. Frontend resolves deep links from type + value.
     * Only URL type includes a resolved url string.
     *
     * @return array{type: string, label: string|null, value: string|null, url: string|null}|null
     */
    public function resolveForStorefront(
        ?CmsCtaTargetType $type,
        ?string $label,
        ?string $value,
    ): ?array {
        if ($type === null || $type === CmsCtaTargetType::None) {
            return null;
        }

        $url = null;
        if ($type === CmsCtaTargetType::Url && $value !== null && $this->isSafeHttpUrl($value)) {
            $url = $value;
        }

        return [
            'type' => $type->value,
            'label' => $label,
            'value' => $type === CmsCtaTargetType::ChinaOrderForm ? 'china_order_form' : $value,
            'url' => $url,
        ];
    }

    private function assertSafeUrl(string $fieldPrefix, string $value): void
    {
        if (! $this->isSafeHttpUrl($value)) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => [
                    'CTA URL must use http or https and cannot use javascript:, data:, or other unsafe schemes.',
                ],
            ]);
        }
    }

    private function isSafeHttpUrl(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 2048) {
            return false;
        }

        $lower = strtolower($value);
        foreach (['javascript:', 'data:', 'vbscript:', 'file:', 'about:'] as $scheme) {
            if (str_starts_with($lower, $scheme)) {
                return false;
            }
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function assertProduct(string $fieldPrefix, string $id, CmsCommerceContext $context): void
    {
        if ($context === CmsCommerceContext::Global) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => [
                    'GLOBAL hero slides cannot target channel-specific products. Use a channel layout or a URL/Page CTA.',
                ],
            ]);
        }

        $product = Product::query()->with('commerceChannel')->find($id);
        if ($product === null) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => ['Referenced product does not exist.'],
            ]);
        }

        $code = $product->commerceChannel?->code;
        $channel = $code instanceof CommerceChannelCode
            ? $code
            : CommerceChannelCode::tryFrom((string) $code);

        $expected = $context->toCommerceChannelCode();
        if ($expected === null || $channel !== $expected) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => [
                    sprintf('Product must belong to the %s commerce channel.', $context->value),
                ],
            ]);
        }
    }

    private function assertStore(string $fieldPrefix, string $id, CmsCommerceContext $context): void
    {
        if ($context === CmsCommerceContext::ChinaImport) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => ['CHINA_IMPORT hero slides cannot target Tanzanian stores.'],
            ]);
        }

        if ($context === CmsCommerceContext::Global) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => [
                    'GLOBAL hero slides cannot target stores. Use a TZ_LOCAL layout or a URL/Page CTA.',
                ],
            ]);
        }

        $store = Store::query()->find($id);
        if ($store === null) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => ['Referenced store does not exist.'],
            ]);
        }

        if (isset($store->is_active) && ! $store->is_active) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => ['Referenced store is not active.'],
            ]);
        }
    }

    private function assertCategory(string $fieldPrefix, string $id, CmsCommerceContext $context): void
    {
        if ($context === CmsCommerceContext::Global) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => [
                    'GLOBAL hero slides cannot target catalog categories. Use a channel layout or a URL/Page CTA.',
                ],
            ]);
        }

        $category = Category::query()->find($id);
        if ($category === null) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => ['Referenced category does not exist.'],
            ]);
        }

        $origin = $category->resolvedOrigin() ?? (
            $category->origin instanceof CatalogOrigin
                ? $category->origin
                : CatalogOrigin::tryFrom((string) $category->origin)
        );
        $expectedOrigin = match ($context) {
            CmsCommerceContext::ChinaImport => CatalogOrigin::China,
            CmsCommerceContext::TzLocal => CatalogOrigin::Tz,
            default => null,
        };

        if ($expectedOrigin !== null && $origin !== $expectedOrigin) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => [
                    sprintf('Category origin must match %s.', $context->value),
                ],
            ]);
        }
    }

    private function assertBrand(string $fieldPrefix, string $id, CmsCommerceContext $context): void
    {
        $brand = Brand::query()->find($id);
        if ($brand === null) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => ['Referenced brand does not exist.'],
            ]);
        }

        if ($context === CmsCommerceContext::Global) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => [
                    'GLOBAL hero slides cannot target brands without a clear global catalog. Use a channel layout or a URL/Page CTA.',
                ],
            ]);
        }

        // Brands lack a channel column — require at least one product on the layout channel.
        $channelCode = $context->toCommerceChannelCode();
        if ($channelCode === null) {
            return;
        }

        $hasProduct = Product::query()
            ->where('brand_id', $brand->id)
            ->whereHas('commerceChannel', fn ($q) => $q->where('code', $channelCode->value))
            ->exists();

        if (! $hasProduct) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => [
                    sprintf('Brand has no products on the %s channel.', $context->value),
                ],
            ]);
        }
    }

    private function assertPromotion(string $fieldPrefix, string $id, CmsCommerceContext $context): void
    {
        $promotion = Promotion::query()->find($id);
        if ($promotion === null) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => ['Referenced promotion does not exist.'],
            ]);
        }

        if ($promotion->status === PromotionStatus::Expired) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => ['Referenced promotion is expired.'],
            ]);
        }

        // Promotions are not channel-scoped as a first-class FK. Allow under all contexts
        // after existence checks; do not guess channel from rules JSON.
        unset($context);
    }

    private function assertPage(string $fieldPrefix, string $id, CmsCommerceContext $context): void
    {
        $page = Page::query()->find($id);
        if ($page === null) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => ['Referenced page does not exist.'],
            ]);
        }

        if (! $page->is_published) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_value' => ['Referenced page is not published.'],
            ]);
        }

        // Pages are context-neutral shared content — allowed for GLOBAL and channel layouts.
        unset($context);
    }

    private function assertChinaOrderForm(string $fieldPrefix, CmsCommerceContext $context): void
    {
        if ($context === CmsCommerceContext::TzLocal) {
            throw ValidationException::withMessages([
                $fieldPrefix.'_type' => ['CHINA_ORDER_FORM is not allowed for TZ_LOCAL hero content.'],
            ]);
        }
    }
}
