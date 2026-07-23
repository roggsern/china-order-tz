<?php

namespace App\Services\Pricing;

use App\Enums\PriceTierType;
use App\Enums\PurchasabilityPath;
use App\Enums\VariantPriceType;
use App\Models\ConfigurationPriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Services\Pricing\DTOs\CommercePriceResult;
use App\Services\Pricing\DTOs\CommercePricingContext;
use App\Services\Pricing\DTOs\PriceStageResult;

/**
 * Canonical Catalog → Quote unit-price resolver (ADR 054 / Phase 2A-2B-2).
 *
 * Shared by ResolvePrice (quotes) and ResolveCartPurchasable (cart).
 * Pipeline: base catalog → configuration/variant catalog → MOQ → reserved hooks.
 */
final class CommercePricingResolver
{
    /**
     * Full commerce unit price (Quote = Cart). Prefer this over partial helpers.
     */
    public function resolveCommerceUnitPrice(
        Product $product,
        ?ProductVariant $variant = null,
        ?CommercePricingContext $context = null,
    ): CommercePriceResult {
        $context ??= new CommercePricingContext;
        $currency = $context->currency();
        $steps = [];

        $base = $this->resolveSimpleProductPrice($product, $context);
        $steps[] = new PriceStageResult(
            stage: 'base',
            label: 'Base Price',
            unitPrice: $base->unitPrice,
            applied: true,
            note: 'Product base price',
            meta: ['source' => $base->source, 'product_id' => $product->id],
        );

        $current = $base->unitPrice;
        $path = PurchasabilityPath::Simple;
        $source = $base->source;
        $resolved = true;
        $extraMeta = ['product_id' => $product->id];

        [$current, $path, $source, $resolved, $configStep, $extraMeta] = $this->applyConfigurationCatalogStep(
            $product,
            $variant,
            $context,
            $current,
            $path,
            $source,
            $resolved,
            $extraMeta,
        );
        $steps[] = $configStep;

        if (! $resolved) {
            return new CommercePriceResult(
                resolved: false,
                unitPrice: '0.00',
                currency: $currency,
                path: $path,
                source: $source,
                meta: array_merge($extraMeta, [
                    'steps' => array_map(fn (PriceStageResult $s) => $s->toArray(), $steps),
                    'stage_results' => $steps,
                ]),
            );
        }

        $afterCatalog = new CommercePriceResult(
            resolved: true,
            unitPrice: $current,
            currency: $currency,
            path: $path,
            source: $source,
            meta: $extraMeta,
        );

        $afterMoq = $this->applyMoqExtension($afterCatalog, $context, $product, $variant);
        $steps[] = $this->moqStepFromResult($afterCatalog, $afterMoq, $context);

        $final = $this->applyCustomerPricingExtension($afterMoq, $context);
        $final = $this->applyChannelPricingExtension($final, $context);
        $final = $this->applyRegionalPricingExtension($final, $context);

        return new CommercePriceResult(
            resolved: $final->resolved,
            unitPrice: $final->unitPrice,
            currency: $final->currency,
            path: $final->path,
            source: $final->source,
            meta: array_merge($final->meta, [
                'steps' => array_map(fn (PriceStageResult $s) => $s->toArray(), $steps),
                'stage_results' => $steps,
            ]),
        );
    }

    /**
     * @deprecated Prefer resolveCommerceUnitPrice for Quote/Cart parity.
     */
    public function resolveUnitPrice(
        Product $product,
        ?ProductVariant $variant = null,
        ?CommercePricingContext $context = null,
    ): CommercePriceResult {
        return $this->resolveCommerceUnitPrice($product, $variant, $context);
    }

    /**
     * Simple Product Catalog Price SSoT: products.price (catalog load only; no MOQ).
     */
    public function resolveSimpleProductPrice(
        Product $product,
        ?CommercePricingContext $context = null,
    ): CommercePriceResult {
        $context ??= new CommercePricingContext;
        $currency = $context->currency();

        return new CommercePriceResult(
            resolved: true,
            unitPrice: $this->formatAmount($product->price),
            currency: $currency,
            path: PurchasabilityPath::Simple,
            source: 'products.price',
            meta: [
                'product_id' => $product->id,
            ],
        );
    }

    /**
     * Variant Product Catalog Price SSoT: variant_prices (retail), optional legacy fallback.
     * Catalog load only; no MOQ.
     */
    public function resolveVariantProductPrice(
        ProductVariant $variant,
        ?CommercePricingContext $context = null,
        ?Product $product = null,
    ): CommercePriceResult {
        $context ??= new CommercePricingContext;
        $currency = $context->currency();
        $product ??= $variant->relationLoaded('product') ? $variant->product : $variant->product()->first();

        $retail = $this->findActiveRetailPrice($variant, $currency);

        if ($retail !== null) {
            return new CommercePriceResult(
                resolved: true,
                unitPrice: $this->formatAmount($retail->amount),
                currency: $currency,
                path: PurchasabilityPath::Variant,
                source: 'variant_price_retail',
                meta: [
                    'product_id' => $product?->id ?? $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'variant_price_id' => $retail->id,
                ],
            );
        }

        if ($context->allowLegacyVariantFallback) {
            $product?->loadMissing('productType');
            $allowsOverride = $product?->productType === null
                || (bool) $product->productType->allows_price_override;

            if ($allowsOverride && $variant->price !== null && (float) $variant->price > 0) {
                return new CommercePriceResult(
                    resolved: true,
                    unitPrice: $this->formatAmount($variant->price),
                    currency: $currency,
                    path: PurchasabilityPath::Variant,
                    source: 'legacy_variant_column',
                    meta: [
                        'product_id' => $product?->id ?? $variant->product_id,
                        'product_variant_id' => $variant->id,
                    ],
                );
            }

            if ($product !== null && (float) $product->price > 0) {
                return new CommercePriceResult(
                    resolved: true,
                    unitPrice: $this->formatAmount($product->price),
                    currency: $currency,
                    path: PurchasabilityPath::Variant,
                    source: 'legacy_product_base',
                    meta: [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                    ],
                );
            }
        }

        return CommercePriceResult::unresolved(
            $currency,
            PurchasabilityPath::Variant,
            'variant_price_missing',
            [
                'product_id' => $product?->id ?? $variant->product_id,
                'product_variant_id' => $variant->id,
            ],
        );
    }

    /**
     * MOQ / quantity tiers — identical for Quote and Cart (ADR 054 Decision 6).
     */
    public function applyMoqExtension(
        CommercePriceResult $current,
        CommercePricingContext $context,
        ?Product $product = null,
        ?ProductVariant $variant = null,
    ): CommercePriceResult {
        if (! $current->resolved) {
            return $current;
        }

        $productId = $product?->id ?? ($current->meta['product_id'] ?? null);
        if ($productId === null) {
            return $current;
        }

        $product ??= Product::query()->with('productType')->find($productId);
        if ($product === null) {
            return $current;
        }

        $product->loadMissing('productType');

        if ($product->productType && ! $product->productType->allows_moq_pricing) {
            return new CommercePriceResult(
                resolved: true,
                unitPrice: $current->unitPrice,
                currency: $current->currency,
                path: $current->path,
                source: $current->source,
                meta: array_merge($current->meta, [
                    'moq_applied' => false,
                    'moq_note' => 'Product type does not allow MOQ / quantity tier pricing',
                ]),
            );
        }

        $variantId = $variant?->id ?? ($current->meta['product_variant_id'] ?? null);
        if ($variant === null && $variantId !== null) {
            $variant = ProductVariant::query()->find($variantId);
        }

        $tier = $this->resolveTier($product, $variant, $context->quantity);

        if ($tier === null) {
            return new CommercePriceResult(
                resolved: true,
                unitPrice: $current->unitPrice,
                currency: $current->currency,
                path: $current->path,
                source: $current->source,
                meta: array_merge($current->meta, [
                    'moq_applied' => false,
                    'moq_note' => 'No quantity tier matched for quantity '.$context->quantity,
                    'quantity' => $context->quantity,
                ]),
            );
        }

        $unitPrice = $tier->resolveUnitPrice($current->unitPrice);
        $tierType = $tier->tier_type ?? PriceTierType::FixedUnit;

        return new CommercePriceResult(
            resolved: true,
            unitPrice: $unitPrice,
            currency: $current->currency,
            path: $current->path,
            source: $current->source,
            meta: array_merge($current->meta, [
                'moq_applied' => true,
                'moq_note' => $tierType === PriceTierType::PercentOff
                    ? 'Quantity tier applied (percent_off='.$tier->discount_percent.'%, min_quantity='.$tier->min_quantity.')'
                    : 'Quantity tier applied (min_quantity='.$tier->min_quantity.')',
                'tier_id' => $tier->id,
                'min_quantity' => $tier->min_quantity,
                'quantity' => $context->quantity,
                'scope' => $tier->product_variant_id ? 'configuration' : 'product',
                'tier_type' => $tierType->value,
                'discount_percent' => $tier->discount_percent,
                'unit_price_before_moq' => $current->unitPrice,
            ]),
        );
    }

    public function applyCustomerPricingExtension(
        CommercePriceResult $current,
        CommercePricingContext $context,
    ): CommercePriceResult {
        return $current;
    }

    public function applyChannelPricingExtension(
        CommercePriceResult $current,
        CommercePricingContext $context,
    ): CommercePriceResult {
        return $current;
    }

    public function applyRegionalPricingExtension(
        CommercePriceResult $current,
        CommercePricingContext $context,
    ): CommercePriceResult {
        return $current;
    }

    /**
     * @deprecated Use resolveCommerceUnitPrice which already applies extensions.
     */
    public function applyReservedExtensions(
        CommercePriceResult $current,
        CommercePricingContext $context,
    ): CommercePriceResult {
        $productId = $current->meta['product_id'] ?? null;
        $product = $productId ? Product::query()->find($productId) : null;
        $variantId = $current->meta['product_variant_id'] ?? null;
        $variant = $variantId ? ProductVariant::query()->find($variantId) : null;

        $current = $this->applyMoqExtension($current, $context, $product, $variant);
        $current = $this->applyCustomerPricingExtension($current, $context);
        $current = $this->applyChannelPricingExtension($current, $context);
        $current = $this->applyRegionalPricingExtension($current, $context);

        return $current;
    }

    /**
     * @param  array<string, mixed>  $extraMeta
     * @return array{0: string, 1: PurchasabilityPath, 2: string, 3: bool, 4: PriceStageResult, 5: array<string, mixed>}
     */
    private function applyConfigurationCatalogStep(
        Product $product,
        ?ProductVariant $variant,
        CommercePricingContext $context,
        string $currentUnitPrice,
        PurchasabilityPath $path,
        string $source,
        bool $resolved,
        array $extraMeta,
    ): array {
        if ($variant === null) {
            return [
                $currentUnitPrice,
                $path,
                $source,
                $resolved,
                new PriceStageResult(
                    stage: 'configuration_override',
                    label: 'Configuration Price Override',
                    unitPrice: $currentUnitPrice,
                    applied: false,
                    note: 'No configuration selected',
                ),
                $extraMeta,
            ];
        }

        $product->loadMissing('productType');
        $extraMeta['product_variant_id'] = $variant->id;

        $catalog = $this->resolveVariantProductPrice($variant, $context, $product);

        if ($catalog->source === 'variant_price_retail') {
            return [
                $catalog->unitPrice,
                PurchasabilityPath::Variant,
                $catalog->source,
                true,
                new PriceStageResult(
                    stage: 'configuration_override',
                    label: 'Configuration Price Override',
                    unitPrice: $catalog->unitPrice,
                    applied: true,
                    note: 'Variant retail Catalog Price (variant_prices)',
                    meta: [
                        'configuration_id' => $variant->id,
                        'configuration_name' => $variant->name,
                        'source' => $catalog->source,
                        'variant_price_id' => $catalog->meta['variant_price_id'] ?? null,
                    ],
                ),
                array_merge($extraMeta, $catalog->meta),
            ];
        }

        if ($catalog->source === 'legacy_variant_column') {
            return [
                $catalog->unitPrice,
                PurchasabilityPath::Variant,
                $catalog->source,
                true,
                new PriceStageResult(
                    stage: 'configuration_override',
                    label: 'Configuration Price Override',
                    unitPrice: $catalog->unitPrice,
                    applied: true,
                    note: 'Configuration-specific price applied',
                    meta: [
                        'configuration_id' => $variant->id,
                        'configuration_name' => $variant->name,
                        'source' => $catalog->source,
                    ],
                ),
                array_merge($extraMeta, $catalog->meta),
            ];
        }

        if ($catalog->source === 'legacy_product_base') {
            return [
                $currentUnitPrice,
                PurchasabilityPath::Variant,
                'products.price',
                true,
                new PriceStageResult(
                    stage: 'configuration_override',
                    label: 'Configuration Price Override',
                    unitPrice: $currentUnitPrice,
                    applied: false,
                    note: $product->productType && ! $product->productType->allows_price_override
                        ? 'Product type does not allow configuration price overrides'
                        : 'Configuration has no price override',
                    meta: ['configuration_id' => $variant->id],
                ),
                $extraMeta,
            ];
        }

        if (! $context->allowLegacyVariantFallback) {
            return [
                '0.00',
                PurchasabilityPath::Variant,
                'variant_price_missing',
                false,
                new PriceStageResult(
                    stage: 'configuration_override',
                    label: 'Configuration Price Override',
                    unitPrice: $currentUnitPrice,
                    applied: false,
                    note: 'No active retail VariantPrice for configuration',
                    meta: ['configuration_id' => $variant->id],
                ),
                $extraMeta,
            ];
        }

        return [
            $currentUnitPrice,
            $path,
            $source,
            $resolved,
            new PriceStageResult(
                stage: 'configuration_override',
                label: 'Configuration Price Override',
                unitPrice: $currentUnitPrice,
                applied: false,
                note: 'Configuration has no price override',
                meta: ['configuration_id' => $variant->id],
            ),
            $extraMeta,
        ];
    }

    private function moqStepFromResult(
        CommercePriceResult $before,
        CommercePriceResult $after,
        CommercePricingContext $context,
    ): PriceStageResult {
        $applied = (bool) ($after->meta['moq_applied'] ?? false);

        return new PriceStageResult(
            stage: 'quantity_tier',
            label: 'MOQ / Quantity Tier',
            unitPrice: $after->unitPrice,
            applied: $applied,
            note: $after->meta['moq_note'] ?? null,
            meta: array_filter([
                'tier_id' => $after->meta['tier_id'] ?? null,
                'min_quantity' => $after->meta['min_quantity'] ?? null,
                'quantity' => $context->quantity,
                'scope' => $after->meta['scope'] ?? null,
                'tier_type' => $after->meta['tier_type'] ?? null,
                'discount_percent' => $after->meta['discount_percent'] ?? null,
            ], fn ($v) => $v !== null),
        );
    }

    private function resolveTier(
        Product $product,
        ?ProductVariant $configuration,
        int $quantity,
    ): ?ConfigurationPriceTier {
        if ($configuration !== null) {
            $configTier = ConfigurationPriceTier::query()
                ->where('product_variant_id', $configuration->id)
                ->where('min_quantity', '<=', $quantity)
                ->orderByDesc('min_quantity')
                ->first();

            if ($configTier !== null) {
                return $configTier;
            }
        }

        return ConfigurationPriceTier::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->where('min_quantity', '<=', $quantity)
            ->orderByDesc('min_quantity')
            ->first();
    }

    private function findActiveRetailPrice(ProductVariant $variant, string $currency): ?VariantPrice
    {
        $prices = $variant->relationLoaded('prices')
            ? $variant->prices
            : $variant->prices()->get();

        $retail = $prices->first(function ($price) use ($currency) {
            $type = $price->price_type instanceof VariantPriceType
                ? $price->price_type
                : VariantPriceType::tryFrom((string) $price->price_type);

            return $type === VariantPriceType::Retail
                && strtoupper((string) $price->currency) === $currency
                && $price->isCurrentlyActive();
        });

        if ($retail !== null) {
            return $retail;
        }

        return $variant->retailPrice($currency);
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
