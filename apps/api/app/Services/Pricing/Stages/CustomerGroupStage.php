<?php

namespace App\Services\Pricing\Stages;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\Contracts\PriceStageInterface;
use App\Services\Pricing\DTOs\PriceQuoteInput;
use App\Services\Pricing\DTOs\PriceStageResult;

/**
 * Reserved stage — implemented in Phase F. Pass-through only.
 */
final class CustomerGroupStage implements PriceStageInterface
{
    public function key(): string
    {
        return 'customer_group';
    }

    public function label(): string
    {
        return 'Customer Group';
    }

    public function apply(
        Product $product,
        ?ProductVariant $configuration,
        PriceQuoteInput $input,
        string $currentUnitPrice,
    ): PriceStageResult {
        return new PriceStageResult(
            stage: $this->key(),
            label: $this->label(),
            unitPrice: $currentUnitPrice,
            applied: false,
            note: 'Reserved — customer group pricing not implemented yet',
            meta: [
                'customer_group_id' => $input->customerGroupId,
                'reserved' => true,
            ],
        );
    }
}
