<?php

namespace App\Actions\AdminBrands;

use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Support\Str;

class UpdateBrandAction
{
    public function handle(UpdateBrandRequest $request, Brand $brand): Brand
    {
        $validated = $request->validated();

        $data = ['name' => $validated['name']];

        if ($validated['name'] !== $brand->name) {
            $data['slug'] = $this->generateUniqueSlug($validated['name'], $brand->id);
        }

        foreach (['description', 'logo', 'banner', 'website', 'country', 'is_featured', 'sort_order', 'is_active'] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $validated[$field];
            }
        }

        $brand->update($data);

        if (array_key_exists('category_ids', $validated)) {
            $brand->categories()->sync($validated['category_ids'] ?? []);
        }

        return $brand->fresh(['categories'])->loadCount('products');
    }

    private function generateUniqueSlug(string $name, string $ignoreBrandId): string
    {
        $slug = Str::slug($name);
        $original = $slug !== '' ? $slug : 'brand';
        $slug = $original;
        $counter = 1;

        while (
            Brand::query()
                ->where('slug', $slug)
                ->where('id', '!=', $ignoreBrandId)
                ->exists()
        ) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
