<?php

namespace Database\Seeders;

use App\Enums\VariantPriceType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds Pricing Engine rows for catalog product variants.
 * Does not write business prices onto product_variants.price.
 */
class VariantPriceSeeder extends Seeder
{
    public function run(): void
    {
        ProductVariant::query()
            ->with('product.catalogProductType')
            ->whereHas('product', fn ($query) => $query->whereNotNull('catalog_product_type_id'))
            ->get()
            ->each(function (ProductVariant $variant) {
                if (VariantPrice::query()->where('product_variant_id', $variant->id)->exists()) {
                    return;
                }

                $product = $variant->product;
                if ($product === null) {
                    return;
                }

                $bucket = $this->bucket($product);
                $retailTzs = $this->retailTzs($bucket, $product->name, $variant->name);
                $wholesaleTzs = round($retailTzs * 0.85, 2);
                $retailUsd = round($retailTzs / 2500, 2);
                $wholesaleUsd = round($wholesaleTzs / 2500, 2);

                $this->createPrice($variant, VariantPriceType::Retail, 'TZS', $retailTzs, 1);
                $this->createPrice($variant, VariantPriceType::Wholesale, 'TZS', $wholesaleTzs, 5);
                $this->createPrice($variant, VariantPriceType::Retail, 'USD', $retailUsd, 1);
                $this->createPrice($variant, VariantPriceType::Wholesale, 'USD', $wholesaleUsd, 5);

                // Demo VIP schedule for flagship phones / featured fashion.
                if ($bucket === 'phones' && str_contains(Str::lower($product->name), 'galaxy s25')) {
                    $this->createPrice(
                        $variant,
                        VariantPriceType::Vip,
                        'USD',
                        round($retailUsd * 0.9, 2),
                        1,
                        now()->subDay(),
                        now()->addMonths(3),
                    );
                }
            });
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

    private function retailTzs(string $bucket, string $productName, ?string $variantName): float
    {
        $base = match ($bucket) {
            'phones' => 1_850_000,
            'fashion' => 145_000,
            default => 890_000,
        };

        $name = Str::lower($productName.' '.($variantName ?? ''));

        if (str_contains($name, '512')) {
            $base *= 1.25;
        } elseif (str_contains($name, '256')) {
            $base *= 1.12;
        } elseif (str_contains($name, '128')) {
            $base *= 1.0;
        }

        if (str_contains($name, 'iphone')) {
            $base *= 1.15;
        }

        if (str_contains($name, 'qsc') || str_contains($name, 'line array')) {
            $base *= 1.4;
        }

        return round($base, 2);
    }

    private function createPrice(
        ProductVariant $variant,
        VariantPriceType $type,
        string $currency,
        float $amount,
        int $minimumQuantity,
        mixed $startsAt = null,
        mixed $endsAt = null,
    ): void {
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => $type,
            'currency' => $currency,
            'amount' => $amount,
            'compare_at_price' => round($amount * 1.12, 2),
            'cost_price' => round($amount * 0.62, 2),
            'minimum_quantity' => $minimumQuantity,
            'is_active' => true,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
