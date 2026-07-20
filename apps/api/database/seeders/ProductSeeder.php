<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductTypeAttribute;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\ProductConfiguration\ResolveTypeFromCategory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::query()->exists()) {
            $this->command?->info('ProductSeeder skipped: products already exist.');

            return;
        }

        $categories = Category::query()->whereNotNull('parent_id')->get();
        $brands = Brand::all();
        $suppliers = Supplier::all();
        $resolveType = app(ResolveTypeFromCategory::class);

        if ($categories->isEmpty() || $brands->isEmpty() || $suppliers->isEmpty()) {
            $this->command?->warn('ProductSeeder skipped: categories, brands, or suppliers are missing.');

            return;
        }

        Product::factory(30)->demo()->create([
            'category_id' => fn () => $categories->random()->id,
            'brand_id' => fn () => $brands->random()->id,
            'supplier_id' => fn () => $suppliers->random()->id,
        ])->each(function (Product $product) use ($resolveType) {
            $product->loadMissing(['supplier', 'category.parent']);

            $type = $resolveType->handle($product->category);
            if ($type !== null) {
                $product->update(['product_type_id' => $type->id]);
            }

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

            if ($type === null || ! $type->has_configurations || ! fake()->boolean(60)) {
                return;
            }

            $configAttributes = ProductTypeAttribute::query()
                ->where('product_type_id', $type->id)
                ->where('participates_in_configuration', true)
                ->with('attribute.values')
                ->orderBy('sort_order')
                ->get();

            if ($configAttributes->isEmpty()) {
                return;
            }

            $selectedValues = $configAttributes
                ->map(function (ProductTypeAttribute $typeAttribute) {
                    $values = $typeAttribute->attribute?->values;

                    return $values !== null && $values->isNotEmpty()
                        ? $values->random()
                        : null;
                })
                ->filter();

            if ($selectedValues->isEmpty()) {
                return;
            }

            $variant = ProductVariant::factory()->create([
                'product_id' => $product->id,
                'name' => $selectedValues->pluck('value')->implode(' / '),
            ]);

            $variant->attributeValues()->sync($selectedValues->pluck('id')->all());

            Inventory::factory()->forVariant($variant)->create([
                'quantity' => fake()->numberBetween(5, 100),
            ]);
        });
    }
}
