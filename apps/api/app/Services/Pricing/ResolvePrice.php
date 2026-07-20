<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\Contracts\PriceStageInterface;
use App\Services\Pricing\DTOs\PriceBreakdown;
use App\Services\Pricing\DTOs\PriceQuoteInput;
use App\Services\Pricing\DTOs\PriceStageResult;
use App\Services\Pricing\Stages\BasePriceStage;
use App\Services\Pricing\Stages\ConfigurationOverrideStage;
use App\Services\Pricing\Stages\CouponStage;
use App\Services\Pricing\Stages\CustomerGroupStage;
use App\Services\Pricing\Stages\PromotionStage;
use App\Services\Pricing\Stages\QuantityTierStage;
use Illuminate\Validation\ValidationException;

/**
 * Pricing Rules Engine.
 *
 * Fixed pipeline order (do not reorder without architecture approval):
 * Base → Configuration Override → MOQ/Qty Tier → Promotion → Coupon → Customer Group → Final
 */
class ResolvePrice
{
    /** @var list<PriceStageInterface> */
    private array $stages;

    public function __construct(
        ?BasePriceStage $base = null,
        ?ConfigurationOverrideStage $configurationOverride = null,
        ?QuantityTierStage $quantityTier = null,
        ?PromotionStage $promotion = null,
        ?CouponStage $coupon = null,
        ?CustomerGroupStage $customerGroup = null,
    ) {
        $this->stages = [
            $base ?? new BasePriceStage,
            $configurationOverride ?? new ConfigurationOverrideStage,
            $quantityTier ?? new QuantityTierStage,
            $promotion ?? new PromotionStage,
            $coupon ?? new CouponStage,
            $customerGroup ?? new CustomerGroupStage,
        ];
    }

    public function handle(Product $product, PriceQuoteInput $input): PriceBreakdown
    {
        if ($input->quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be at least 1.'],
            ]);
        }

        $configuration = $this->resolveConfiguration($product, $input->configurationId);

        $stages = [];
        $unitPrice = '0.00';

        foreach ($this->stages as $stage) {
            $result = $stage->apply($product, $configuration, $input, $unitPrice);
            $unitPrice = $result->unitPrice;
            $stages[] = $result;
        }

        $stages[] = new PriceStageResult(
            stage: 'final',
            label: 'Final Price',
            unitPrice: $unitPrice,
            applied: true,
            note: 'Resolved unit price after all pricing stages',
        );

        $lineTotal = number_format((float) $unitPrice * $input->quantity, 2, '.', '');

        return new PriceBreakdown(
            productId: $product->id,
            configurationId: $configuration?->id,
            quantity: $input->quantity,
            currency: 'TZS',
            unitPrice: $unitPrice,
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
