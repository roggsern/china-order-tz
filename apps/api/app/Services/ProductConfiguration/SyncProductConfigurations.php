<?php

namespace App\Services\ProductConfiguration;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Models\ProductVariant;
use App\Services\Pricing\SyncConfigurationPriceTiers;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Persists sellable configurations for a product from metadata-driven rows.
 */
class SyncProductConfigurations
{
    public function __construct(
        private readonly GenerateValidConfigurations $generateValidConfigurations,
        private readonly GenerateConfigurationSku $generateConfigurationSku,
        private readonly SyncConfigurationPriceTiers $syncConfigurationPriceTiers,
    ) {}

    /**
     * @param  list<array{
     *     id?: string|null,
     *     attribute_value_ids: list<string>,
     *     sku?: string|null,
     *     stock_quantity: int,
     *     price?: float|string|null,
     *     barcode?: string|null,
     *     price_tiers?: list<array{min_quantity: int, unit_price: float|int|string}>
     * }>  $rows
     */
    public function handle(Product $product, ProductType $productType, array $rows): Product
    {
        return DB::transaction(function () use ($product, $productType, $rows) {
            $keepIds = [];

            foreach (array_values($rows) as $index => $row) {
                $selections = $this->selectionsFromValueIds($row['attribute_value_ids'] ?? []);

                if (! $this->generateValidConfigurations->isValid($productType, $selections)) {
                    throw ValidationException::withMessages([
                        "configurations.{$index}" => ['Configuration combination is not allowed by attribute dependency rules.'],
                    ]);
                }

                $name = $this->configurationName($row['attribute_value_ids']);
                $sku = filled($row['sku'] ?? null)
                    ? (string) $row['sku']
                    : $this->generateConfigurationSku->handle(
                        $productType,
                        (string) $product->sku,
                        $selections,
                        $index + 1,
                    );

                $sku = $this->ensureUniqueSku($sku, $row['id'] ?? null);

                $variant = null;
                if (filled($row['id'] ?? null)) {
                    $variant = ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->where('id', $row['id'])
                        ->first();
                }

                $payload = [
                    'sku' => $sku,
                    'name' => $name,
                    'price' => $row['price'] ?? null,
                    'barcode' => $row['barcode'] ?? null,
                    'is_active' => true,
                ];

                if ($variant === null) {
                    $variant = ProductVariant::query()->create([
                        'product_id' => $product->id,
                        ...$payload,
                    ]);
                } else {
                    $variant->update($payload);
                }

                $variant->attributeValues()->sync($row['attribute_value_ids']);

                Inventory::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                    ],
                    [
                        'quantity' => max(0, (int) ($row['stock_quantity'] ?? 0)),
                    ],
                );

                if (array_key_exists('price_tiers', $row)) {
                    $this->syncConfigurationPriceTiers->handle(
                        $product,
                        $variant,
                        is_array($row['price_tiers']) ? $row['price_tiers'] : [],
                    );
                }

                $keepIds[] = $variant->id;
            }

            $obsolete = ProductVariant::query()
                ->where('product_id', $product->id)
                ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds), fn ($q) => $q)
                ->get();

            foreach ($obsolete as $variant) {
                Inventory::query()
                    ->where('product_variant_id', $variant->id)
                    ->delete();
                $variant->attributeValues()->detach();
                $variant->delete();
            }

            return $product->fresh([
                'variants.attributeValues.attribute',
                'variants.inventory',
                'variants.priceTiers',
                'inventory',
            ]);
        });
    }

    /**
     * @param  list<string>  $valueIds
     * @return array<string, string>
     */
    private function selectionsFromValueIds(array $valueIds): array
    {
        return ProductAttributeValue::query()
            ->whereIn('id', $valueIds)
            ->get()
            ->mapWithKeys(fn (ProductAttributeValue $value) => [
                $value->product_attribute_id => $value->id,
            ])
            ->all();
    }

    /**
     * @param  list<string>  $valueIds
     */
    private function configurationName(array $valueIds): string
    {
        return ProductAttributeValue::query()
            ->whereIn('id', $valueIds)
            ->orderBy('sort_order')
            ->pluck('value')
            ->implode(' / ');
    }

    private function ensureUniqueSku(string $sku, ?string $ignoreVariantId): string
    {
        $candidate = $sku;
        $counter = 1;

        while (
            ProductVariant::query()
                ->where('sku', $candidate)
                ->when($ignoreVariantId, fn ($q) => $q->where('id', '!=', $ignoreVariantId))
                ->exists()
        ) {
            $candidate = $sku.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
