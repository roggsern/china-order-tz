<?php

namespace App\Actions\AdminCatalogProductTypes;

use App\Http\Requests\Admin\UpdateCatalogProductTypeRequest;
use App\Models\CatalogProductType;
use App\Models\Category;
use Illuminate\Support\Str;

class UpdateCatalogProductTypeAction
{
    public function handle(
        UpdateCatalogProductTypeRequest $request,
        CatalogProductType $catalogProductType,
    ): CatalogProductType {
        $validated = $request->validated();
        $parent = Category::query()->findOrFail($validated['subcategory_id']);

        $data = [
            'name' => $validated['name'],
            'subcategory_id' => $parent->id,
        ];

        if (array_key_exists('slug', $validated) && is_string($validated['slug']) && trim($validated['slug']) !== '') {
            $data['slug'] = $this->ensureUniqueSlug(Str::slug($validated['slug']), $catalogProductType->id);
        } elseif (
            $validated['name'] !== $catalogProductType->name
            || $parent->id !== $catalogProductType->subcategory_id
        ) {
            $data['slug'] = $this->ensureUniqueSlug(
                Str::slug($parent->slug.'-'.$validated['name']),
                $catalogProductType->id,
            );
        }

        if (array_key_exists('image', $validated)) {
            $data['image'] = $validated['image'];
        }

        if (array_key_exists('description', $validated)) {
            $data['description'] = $validated['description'];
        }

        if (array_key_exists('sort_order', $validated)) {
            $data['sort_order'] = $validated['sort_order'];
        }

        if (array_key_exists('is_active', $validated)) {
            $data['is_active'] = $validated['is_active'];
        }

        $catalogProductType->update($data);

        return $catalogProductType->fresh([
            'subcategory.parent.department',
            'subcategory.department',
        ])->loadCount(['products', 'attributes']);
    }

    private function ensureUniqueSlug(string $original, string $ignoreId): string
    {
        $slug = $original !== '' ? $original : 'product-type';
        $candidate = $slug;
        $counter = 1;

        while (
            CatalogProductType::query()
                ->where('slug', $candidate)
                ->where('id', '!=', $ignoreId)
                ->exists()
        ) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
