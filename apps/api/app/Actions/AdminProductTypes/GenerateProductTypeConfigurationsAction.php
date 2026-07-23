<?php

namespace App\Actions\AdminProductTypes;

use App\Models\ProductType;
use App\Services\ProductConfiguration\GenerateConfigurationSku;
use App\Services\ProductConfiguration\GenerateValidConfigurations;
use App\Services\ProductConfiguration\SkuPatternRules;

class GenerateProductTypeConfigurationsAction
{
    public function __construct(
        private readonly GenerateValidConfigurations $generateValidConfigurations,
        private readonly GenerateConfigurationSku $generateConfigurationSku,
    ) {}

    /**
     * @param  array<string, list<string>>  $selectedValueIdsByAttribute
     * @return list<array<string, mixed>>
     */
    public function handle(
        ProductType $productType,
        array $selectedValueIdsByAttribute,
        string $baseSku = 'SKU',
        ?float $defaultPrice = null,
    ): array {
        SkuPatternRules::assertValid($productType->sku_pattern);

        $combos = $this->generateValidConfigurations->handle(
            $productType,
            $selectedValueIdsByAttribute,
        );

        return collect($combos)
            ->values()
            ->map(function (array $combo, int $index) use ($productType, $baseSku, $defaultPrice) {
                return [
                    'attribute_value_ids' => $combo['attribute_value_ids'],
                    'selections' => $combo['selections'],
                    'sku' => $this->generateConfigurationSku->handle(
                        $productType,
                        $baseSku,
                        $combo['selections'],
                        $index + 1,
                    ),
                    'stock_quantity' => 0,
                    'price' => $defaultPrice,
                    'barcode' => null,
                ];
            })
            ->all();
    }
}
