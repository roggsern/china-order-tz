<?php

namespace App\Actions\AdminProducts;

use App\Http\Requests\Admin\StoreProductRequest;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProductAction
{
    public function handle(StoreProductRequest $request): Product
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            $product = Product::create([
                'name' => $validated['name'],
                'slug' => $this->generateUniqueSlug($validated['name']),
                'category_id' => $validated['category_id'],
                'brand_id' => $validated['brand_id'] ?? null,
                'sku' => $validated['sku'],
                'price' => $validated['price'],
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['status'],
            ]);

            Inventory::create([
                'product_id' => $product->id,
                'product_variant_id' => null,
                'quantity' => $validated['stock_quantity'],
            ]);

            return $product->load(['category', 'brand', 'inventory']);
        });
    }

    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
