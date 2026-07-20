<?php

namespace App\Actions\AdminBrands;

use App\Http\Requests\Admin\StoreBrandRequest;
use App\Models\Brand;
use Illuminate\Support\Str;

class CreateBrandAction
{
    public function handle(StoreBrandRequest $request): Brand
    {
        $validated = $request->validated();

        $brand = Brand::create([
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($validated['name']),
            'description' => $validated['description'] ?? null,
            'logo' => $validated['logo'] ?? null,
            'banner' => $validated['banner'] ?? null,
            'website' => $validated['website'] ?? null,
            'country' => $validated['country'] ?? null,
            'is_featured' => $validated['is_featured'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (array_key_exists('category_ids', $validated)) {
            $brand->categories()->sync($validated['category_ids'] ?? []);
        }

        return $brand->fresh(['categories'])->loadCount('products');
    }

    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug !== '' ? $slug : 'brand';
        $slug = $original;
        $counter = 1;

        while (Brand::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
