<?php

namespace App\Actions\AdminProducts;

use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateProductAction
{
    public function handle(UpdateProductRequest $request, Product $product): Product
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $product) {
            $productData = [];

            if (array_key_exists('name', $validated)) {
                $productData['name'] = $validated['name'];

                if ($validated['name'] !== $product->name) {
                    $productData['slug'] = $this->generateUniqueSlug($validated['name'], $product->id);
                }
            }

            if (array_key_exists('category_id', $validated)) {
                $productData['category_id'] = $validated['category_id'];
            }

            if (array_key_exists('brand_id', $validated)) {
                $productData['brand_id'] = $validated['brand_id'];
            }

            if (array_key_exists('sku', $validated)) {
                $productData['sku'] = $validated['sku'];
            }

            if (array_key_exists('price', $validated)) {
                $productData['price'] = $validated['price'];
            }

            if (array_key_exists('short_description', $validated)) {
                $productData['short_description'] = $validated['short_description'];
            }

            if (array_key_exists('description', $validated)) {
                $productData['description'] = $validated['description'];
            }

            if (array_key_exists('status', $validated)) {
                $productData['is_active'] = $validated['status'];
            }

            if ($productData !== []) {
                $product->update($productData);
            }

            if (array_key_exists('stock_quantity', $validated)) {
                Inventory::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                    ],
                    [
                        'quantity' => $validated['stock_quantity'],
                    ],
                );
            }

            return $product->fresh()->load(['category', 'brand', 'inventory']);
        });
    }

    private function generateUniqueSlug(string $name, string $ignoreProductId): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (
            Product::query()
                ->where('slug', $slug)
                ->where('id', '!=', $ignoreProductId)
                ->exists()
        ) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
