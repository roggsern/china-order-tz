<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds Inventory Engine rows (MAIN warehouse) for catalog variants.
 * Does not write stock onto product_variants or the legacy inventory table.
 */
class VariantInventorySeeder extends Seeder
{
    public function run(): void
    {
        ProductVariant::query()
            ->with('product.catalogProductType')
            ->whereHas('product', fn ($query) => $query->whereNotNull('catalog_product_type_id'))
            ->get()
            ->each(function (ProductVariant $variant) {
                if (
                    VariantInventory::query()
                        ->where('product_variant_id', $variant->id)
                        ->where('warehouse_code', 'MAIN')
                        ->exists()
                ) {
                    return;
                }

                $product = $variant->product;
                if ($product === null) {
                    return;
                }

                [$onHand, $reserved, $reorderLevel] = $this->stockFor($product, $variant);

                VariantInventory::query()->create([
                    'product_variant_id' => $variant->id,
                    'warehouse_code' => 'MAIN',
                    'on_hand' => $onHand,
                    'reserved' => $reserved,
                    'reorder_level' => $reorderLevel,
                    'safety_stock' => max(0, (int) floor($reorderLevel / 2)),
                    'is_active' => true,
                ]);
            });
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function stockFor(Product $product, ProductVariant $variant): array
    {
        $bucket = $this->bucket($product);
        $name = Str::lower($product->name.' '.($variant->name ?? ''));

        $onHand = match ($bucket) {
            'phones' => match (true) {
                str_contains($name, '512') => 8,
                str_contains($name, '256') => 22,
                str_contains($name, '128') => 45,
                default => 30,
            },
            'fashion' => match (true) {
                str_contains($name, 'xl') || str_contains($name, 'xxl') => 4,
                str_contains($name, 's ') || str_ends_with(trim($name), ' s') || str_contains($name, ' xs') => 12,
                default => 35,
            },
            default => match (true) {
                str_contains($name, 'line array') || str_contains($name, 'qsc') => 3,
                str_contains($name, 'mixer') => 18,
                default => 25,
            },
        };

        // Force some SKUs below reorder for demo.
        if (
            str_contains($name, '512')
            || str_contains($name, 'line array')
            || (str_contains($name, 'xl') && $bucket === 'fashion')
        ) {
            $onHand = min($onHand, 3);
        }

        $reorderLevel = match ($bucket) {
            'phones' => 10,
            'fashion' => 8,
            default => 5,
        };

        $reserved = (int) min($onHand, max(0, (int) floor($onHand * 0.15)));

        return [$onHand, $reserved, $reorderLevel];
    }

    private function bucket(Product $product): string
    {
        $name = Str::lower($product->name.' '.($product->catalogProductType?->name ?? ''));

        if (str_contains($name, 'phone') || str_contains($name, 'iphone') || str_contains($name, 'galaxy') || str_contains($name, 'pixel')) {
            return 'phones';
        }

        if (
            str_contains($name, 'shirt')
            || str_contains($name, 'dress')
            || str_contains($name, 'sneaker')
            || str_contains($name, 'nike')
            || str_contains($name, 'adidas')
            || str_contains($name, 'zara')
            || str_contains($name, 'fashion')
            || str_contains($name, 'jeans')
        ) {
            return 'fashion';
        }

        return 'audio';
    }
}
