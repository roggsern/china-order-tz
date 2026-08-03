<?php

namespace App\Actions\AdminChinaCommercialStock;

use App\Models\ChinaCommercialStock;
use App\Models\Product;
use App\Services\China\Procurement\ChinaCommercialStockService;
use App\Services\Commerce\CommerceChannelResolver;
use Illuminate\Validation\ValidationException;

final class GetProductCommercialStockAction
{
    public function __construct(
        private readonly ChinaCommercialStockService $commercialStock,
        private readonly CommerceChannelResolver $commerceChannels,
    ) {}

    /**
     * @return array{
     *     path: string,
     *     simple: array<string, mixed>|null,
     *     variants: list<array<string, mixed>>
     * }
     */
    public function handle(Product $product): array
    {
        $product->loadMissing(['commerceChannel', 'variants']);

        $this->assertChinaImportProduct($product);

        $simpleRow = $this->commercialStock->findForProduct($product, null);
        $variantRows = ChinaCommercialStock::query()
            ->where('product_id', $product->id)
            ->whereNotNull('product_variant_id')
            ->get()
            ->keyBy('product_variant_id');

        $variants = $product->variants
            ->sortBy('sort_order')
            ->values()
            ->map(function ($variant) use ($variantRows) {
                /** @var ChinaCommercialStock|null $row */
                $row = $variantRows->get($variant->id);

                return [
                    'variant_id' => $variant->id,
                    'name' => $variant->name,
                    'sku' => $variant->sku,
                    'is_active' => (bool) $variant->is_active,
                    'available_quantity' => (int) ($row?->available_quantity ?? 0),
                    'reserved_quantity' => (int) ($row?->reserved_quantity ?? 0),
                    'ordered_quantity' => (int) ($row?->ordered_quantity ?? 0),
                    'commercial_stock_id' => $row?->id,
                ];
            })
            ->all();

        $hasVariants = $product->variants->isNotEmpty();

        return [
            'path' => $hasVariants ? 'variant' : 'simple',
            'simple' => $hasVariants ? null : $this->serializeSimpleRow($simpleRow),
            'variants' => $hasVariants ? $variants : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSimpleRow(?ChinaCommercialStock $row): array
    {
        return [
            'commercial_stock_id' => $row?->id,
            'available_quantity' => (int) ($row?->available_quantity ?? 0),
            'reserved_quantity' => (int) ($row?->reserved_quantity ?? 0),
            'ordered_quantity' => (int) ($row?->ordered_quantity ?? 0),
        ];
    }

    private function assertChinaImportProduct(Product $product): void
    {
        if ($this->commerceChannels->isChinaImportProduct($product)) {
            return;
        }

        throw ValidationException::withMessages([
            'commerce_channel' => ['Commercial stock is only available for CHINA_IMPORT products.'],
        ]);
    }
}
