<?php

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::query()->whereNotNull('parent_id')->get();
        $brands = Brand::all();
        $suppliers = Supplier::all();

        $colorAttribute = ProductAttribute::query()->updateOrCreate(
            ['slug' => 'color'],
            [
                'name' => 'Color',
                'type' => AttributeType::Color,
                'is_filterable' => true,
                'sort_order' => 1,
            ]
        );

        $sizeAttribute = ProductAttribute::query()->updateOrCreate(
            ['slug' => 'size'],
            [
                'name' => 'Size',
                'type' => AttributeType::Select,
                'is_filterable' => true,
                'sort_order' => 2,
            ]
        );

        $colors = collect([
            ['value' => 'Black', 'color_code' => '#000000'],
            ['value' => 'White', 'color_code' => '#FFFFFF'],
            ['value' => 'Blue', 'color_code' => '#2563EB'],
            ['value' => 'Red', 'color_code' => '#DC2626'],
        ])->map(function (array $color, int $index) use ($colorAttribute) {
            return ProductAttributeValue::query()->updateOrCreate(
                [
                    'product_attribute_id' => $colorAttribute->id,
                    'slug' => strtolower($color['value']),
                ],
                [
                    'value' => $color['value'],
                    'color_code' => $color['color_code'],
                    'sort_order' => $index,
                ]
            );
        });

        $sizes = collect(['S', 'M', 'L', 'XL'])->map(function (string $size, int $index) use ($sizeAttribute) {
            return ProductAttributeValue::query()->updateOrCreate(
                [
                    'product_attribute_id' => $sizeAttribute->id,
                    'slug' => strtolower($size),
                ],
                [
                    'value' => $size,
                    'sort_order' => $index,
                ]
            );
        });

        Product::factory(30)->create([
            'category_id' => fn () => $categories->random()->id,
            'brand_id' => fn () => $brands->random()->id,
            'supplier_id' => fn () => $suppliers->random()->id,
        ])->each(function (Product $product) use ($colors, $sizes) {
            $product->loadMissing('supplier');

            if ($product->isFromChina()) {
                $product->update([
                    'air_shipping_price' => fake()->randomFloat(2, 3000, 15000),
                    'sea_shipping_price' => fake()->randomFloat(2, 1500, 8000),
                ]);
            }

            ProductImage::factory()->primary()->create(['product_id' => $product->id]);
            ProductImage::factory(2)->create(['product_id' => $product->id]);

            Inventory::factory()->create([
                'product_id' => $product->id,
                'product_variant_id' => null,
                'quantity' => fake()->numberBetween(10, 200),
            ]);

            if (fake()->boolean(60)) {
                $color = $colors->random();
                $size = $sizes->random();

                $variant = ProductVariant::factory()->create([
                    'product_id' => $product->id,
                    'name' => "{$color->value} / {$size->value}",
                ]);

                $variant->attributeValues()->sync([$color->id, $size->id]);

                Inventory::factory()->forVariant($variant)->create([
                    'quantity' => fake()->numberBetween(5, 100),
                ]);
            }
        });
    }
}
