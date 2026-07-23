<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\DTOs\CommercePricingContext;
use App\Services\Pricing\DTOs\PriceBreakdown;
use App\Services\Pricing\DTOs\PriceQuoteInput;
use App\Services\Pricing\DTOs\PriceStageResult;
use Illuminate\Validation\ValidationException;

/**
 * Pricing Rules Engine (quote pipeline) — ADR 054 / Phase 2A-2B-2.
 *
 * Unit price is resolved solely by CommercePricingResolver (Quote = Cart).
 * Public breakdown still exposes base → configuration → MOQ → reserved stages.
 */
class ResolvePrice
{
    public function __construct(
        private readonly CommercePricingResolver $commercePricingResolver,
    ) {}

    public function handle(Product $product, PriceQuoteInput $input): PriceBreakdown
    {
        if ($input->quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be at least 1.'],
            ]);
        }

        $configuration = $this->resolveConfiguration($product, $input->configurationId);

        $resolved = $this->commercePricingResolver->resolveCommerceUnitPrice(
            $product,
            $configuration,
            new CommercePricingContext(
                currency: 'TZS',
                quantity: $input->quantity,
                allowLegacyVariantFallback: true,
                customerGroupId: $input->customerGroupId,
            ),
        );

        if (! $resolved->resolved) {
            throw ValidationException::withMessages([
                'configuration_id' => ['No resolvable unit price for this product configuration.'],
            ]);
        }

        /** @var list<PriceStageResult> $pipelineStages */
        $pipelineStages = $resolved->meta['stage_results'] ?? [];

        $stages = $pipelineStages;

        // Reserved stages — still reported; pricing authority remains CommercePricingResolver.
        $stages[] = new PriceStageResult(
            stage: 'promotion',
            label: 'Promotion',
            unitPrice: $resolved->unitPrice,
            applied: false,
            note: 'Reserved — discounts apply via DiscountResolver at checkout',
        );
        $stages[] = new PriceStageResult(
            stage: 'coupon',
            label: 'Coupon',
            unitPrice: $resolved->unitPrice,
            applied: false,
            note: 'Reserved — coupons apply via DiscountResolver at checkout',
        );
        $stages[] = new PriceStageResult(
            stage: 'customer_group',
            label: 'Customer Group',
            unitPrice: $resolved->unitPrice,
            applied: false,
            note: 'Reserved customer pricing extension',
        );
        $stages[] = new PriceStageResult(
            stage: 'final',
            label: 'Final Price',
            unitPrice: $resolved->unitPrice,
            applied: true,
            note: 'Resolved unit price after all pricing stages',
        );

        $lineTotal = number_format((float) $resolved->unitPrice * $input->quantity, 2, '.', '');

        return new PriceBreakdown(
            productId: $product->id,
            configurationId: $configuration?->id,
            quantity: $input->quantity,
            currency: $resolved->currency,
            unitPrice: $resolved->unitPrice,
            lineTotal: $lineTotal,
            stages: $stages,
        );
    }

    private function resolveConfiguration(Product $product, ?string $configurationId): ?ProductVariant
    {
        if ($configurationId === null) {
            return null;
        }

        $configuration = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('id', $configurationId)
            ->first();

        if ($configuration === null) {
            throw ValidationException::withMessages([
                'configuration_id' => ['Configuration does not belong to this product.'],
            ]);
        }

        return $configuration;
    }
}
