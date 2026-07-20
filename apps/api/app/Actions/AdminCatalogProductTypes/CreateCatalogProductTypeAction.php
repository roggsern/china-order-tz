<?php

namespace App\Actions\AdminCatalogProductTypes;

use App\Http\Requests\Admin\StoreCatalogProductTypeRequest;
use App\Models\CatalogProductType;
use App\Models\Category;
use Illuminate\Support\Str;

class CreateCatalogProductTypeAction
{
    public function handle(StoreCatalogProductTypeRequest $request): CatalogProductType
    {
        $validated = $request->validated();
        $parent = Category::query()->findOrFail($validated['subcategory_id']);

        $slugSource = isset($validated['slug']) && is_string($validated['slug']) && trim($validated['slug']) !== ''
            ? $validated['slug']
            : $parent->slug.'-'.$validated['name'];

        return CatalogProductType::create([
            'subcategory_id' => $parent->id,
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($slugSource),
            'image' => $validated['image'] ?? null,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ])->fresh([
            'subcategory.parent.department',
            'subcategory.department',
        ]);
    }

    private function generateUniqueSlug(string $value): string
    {
        $slug = Str::slug($value);
        $original = $slug !== '' ? $slug : 'product-type';
        $candidate = $original;
        $counter = 1;

        while (CatalogProductType::where('slug', $candidate)->exists()) {
            $candidate = $original.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
