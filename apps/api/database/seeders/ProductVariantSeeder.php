<?php

namespace Database\Seeders;

use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\GenerateVariantSku;
use App\Services\Catalog\SyncVariantCatalogAttributeValues;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds purchasable catalog variants for Product Core records.
 * Price/stock remain placeholders (null) for future modules.
 */
class ProductVariantSeeder extends Seeder
{
    public function __construct(
        private readonly SyncVariantCatalogAttributeValues $syncAttributeValues,
        private readonly GenerateVariantSku $generateVariantSku,
    ) {}

    public function run(): void
    {
        Product::query()
            ->with(['catalogProductType.attributes.options'])
            ->whereNotNull('catalog_product_type_id')
            ->get()
            ->each(function (Product $product) {
                // Skip products that already have catalog-driven variants.
                $hasCatalogVariants = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->whereHas('catalogAttributeValues')
                    ->exists();

                if ($hasCatalogVariants) {
                    return;
                }

                $assigned = $product->catalogProductType?->attributes ?? collect();
                if ($assigned->isEmpty()) {
                    $this->seedDefaultOnly($product);

                    return;
                }

                $name = Str::lower($product->name);
                $typeName = Str::lower((string) $product->catalogProductType?->name);

                if ($this->isPhone($name, $typeName)) {
                    $this->seedPhoneVariants($product, $assigned);
                } elseif ($this->isFashion($name, $typeName)) {
                    $this->seedFashionVariants($product, $assigned);
                } else {
                    // Audio / other: single default purchasable unit.
                    $this->seedDefaultOnly($product);
                }
            });
    }

    private function isPhone(string $name, string $typeName): bool
    {
        return str_contains($typeName, 'phone')
            || str_contains($name, 'iphone')
            || str_contains($name, 'galaxy')
            || str_contains($name, 'pixel')
            || str_contains($name, 'camon')
            || str_contains($name, 'redmi');
    }

    private function isFashion(string $name, string $typeName): bool
    {
        return str_contains($typeName, 'shirt')
            || str_contains($typeName, 'dress')
            || str_contains($typeName, 'sneaker')
            || str_contains($typeName, 'shoe')
            || str_contains($name, 'nike')
            || str_contains($name, 'adidas')
            || str_contains($name, 'zara')
            || str_contains($name, 'jeans')
            || str_contains($name, 'tee')
            || str_contains($name, 'polo');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CatalogAttribute>  $assigned
     */
    private function seedPhoneVariants(Product $product, $assigned): void
    {
        $storage = $assigned->first(fn (CatalogAttribute $attr) => $attr->slug === 'storage');
        $color = $assigned->first(fn (CatalogAttribute $attr) => $attr->slug === 'color');

        if ($storage === null) {
            $this->seedDefaultOnly($product);

            return;
        }

        $storageLabels = ['128GB', '256GB', '512GB'];
        $colorLabel = str_contains(Str::lower($product->name), 'iphone') ? 'Black' : 'Black';

        $storageOptions = $storage->options
            ->filter(fn (CatalogAttributeOption $option) => in_array($option->value, $storageLabels, true))
            ->values();

        if ($storageOptions->isEmpty()) {
            $storageOptions = $storage->options->take(3)->values();
        }

        $colorOption = $color?->options->first(
            fn (CatalogAttributeOption $option) => strcasecmp($option->value, $colorLabel) === 0,
        ) ?? $color?->options->first();

        foreach ($storageOptions as $index => $storageOption) {
            $labels = array_values(array_filter([
                $colorOption?->value,
                $storageOption->value,
            ]));

            $rows = [
                [
                    'catalog_attribute_id' => $storage->id,
                    'option_id' => $storageOption->id,
                    'value_text' => $storageOption->value,
                ],
            ];

            if ($color !== null && $colorOption !== null) {
                array_unshift($rows, [
                    'catalog_attribute_id' => $color->id,
                    'option_id' => $colorOption->id,
                    'value_text' => $colorOption->value,
                ]);
            }

            $this->createVariant(
                $product,
                implode(' ', $labels),
                $labels,
                $rows,
                $index === 0,
                $index + 1,
                $assigned->keyBy('id'),
            );
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CatalogAttribute>  $assigned
     */
    private function seedFashionVariants(Product $product, $assigned): void
    {
        $size = $assigned->first(fn (CatalogAttribute $attr) => $attr->slug === 'size');
        $color = $assigned->first(fn (CatalogAttribute $attr) => $attr->slug === 'color');

        if ($size === null && $color === null) {
            $this->seedDefaultOnly($product);

            return;
        }

        $isFootwear = str_contains(Str::lower((string) $product->catalogProductType?->name), 'sneaker')
            || str_contains(Str::lower($product->name), 'air max')
            || str_contains(Str::lower($product->name), 'ultraboost')
            || str_contains(Str::lower($product->name), 'loafer');

        if ($isFootwear && $size !== null) {
            // Clothing Size options (S/M/L…) stand in for shoe sizes in seed data.
            $sizeOptions = $size->options
                ->filter(fn (CatalogAttributeOption $option) => in_array($option->value, ['S', 'M', 'L', 'XL'], true))
                ->values();
            if ($sizeOptions->isEmpty()) {
                $sizeOptions = $size->options->take(4)->values();
            }

            foreach ($sizeOptions as $index => $sizeOption) {
                $this->createVariant(
                    $product,
                    $sizeOption->value,
                    [$sizeOption->value],
                    [[
                        'catalog_attribute_id' => $size->id,
                        'option_id' => $sizeOption->id,
                        'value_text' => $sizeOption->value,
                    ]],
                    $index === 0,
                    $index + 1,
                    $assigned->keyBy('id'),
                );
            }

            return;
        }

        // Apparel: Color × Size (subset)
        $colorOptions = ($color?->options ?? collect())
            ->filter(fn (CatalogAttributeOption $option) => in_array($option->value, ['Black', 'Red', 'White'], true))
            ->values();
        if ($colorOptions->isEmpty() && $color !== null) {
            $colorOptions = $color->options->take(2)->values();
        }

        $sizeOptions = ($size?->options ?? collect())
            ->filter(fn (CatalogAttributeOption $option) => in_array($option->value, ['S', 'M', 'L'], true))
            ->values();
        if ($sizeOptions->isEmpty() && $size !== null) {
            $sizeOptions = $size->options->take(3)->values();
        }

        if ($colorOptions->isEmpty() && $sizeOptions->isEmpty()) {
            $this->seedDefaultOnly($product);

            return;
        }

        $index = 0;
        $colors = $colorOptions->isEmpty() ? [null] : $colorOptions->all();
        $sizes = $sizeOptions->isEmpty() ? [null] : $sizeOptions->all();

        foreach ($colors as $colorOption) {
            foreach ($sizes as $sizeOption) {
                $labels = array_values(array_filter([
                    $colorOption?->value,
                    $sizeOption?->value,
                ]));
                $rows = [];
                if ($color !== null && $colorOption !== null) {
                    $rows[] = [
                        'catalog_attribute_id' => $color->id,
                        'option_id' => $colorOption->id,
                        'value_text' => $colorOption->value,
                    ];
                }
                if ($size !== null && $sizeOption !== null) {
                    $rows[] = [
                        'catalog_attribute_id' => $size->id,
                        'option_id' => $sizeOption->id,
                        'value_text' => $sizeOption->value,
                    ];
                }

                $this->createVariant(
                    $product,
                    implode(' ', $labels) ?: 'Default',
                    $labels,
                    $rows,
                    $index === 0,
                    $index + 1,
                    $assigned->keyBy('id'),
                );
                $index++;
            }
        }
    }

    private function seedDefaultOnly(Product $product): void
    {
        $exists = ProductVariant::query()->where('product_id', $product->id)->exists();
        if ($exists) {
            return;
        }

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Default',
            'sku' => $this->generateVariantSku->handle($product, ['DEFAULT']),
            'barcode' => null,
            'price' => null,
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<array<string, mixed>>  $rows
     * @param  \Illuminate\Support\Collection<string, CatalogAttribute>  $allowedById
     */
    private function createVariant(
        Product $product,
        string $name,
        array $labels,
        array $rows,
        bool $isDefault,
        int $sortOrder,
        $allowedById,
    ): void {
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => $name,
            'sku' => $this->generateVariantSku->handle($product, $labels, $sortOrder),
            'barcode' => null,
            'price' => null,
            'is_active' => true,
            'is_default' => $isDefault,
            'sort_order' => $sortOrder,
        ]);

        if ($rows !== []) {
            $this->syncAttributeValues->handle($variant, $rows, $allowedById);
        }
    }
}
