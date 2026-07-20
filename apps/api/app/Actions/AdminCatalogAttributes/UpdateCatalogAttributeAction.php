<?php

namespace App\Actions\AdminCatalogAttributes;

use App\Http\Requests\Admin\UpdateCatalogAttributeRequest;
use App\Models\CatalogAttribute;
use Illuminate\Support\Str;

class UpdateCatalogAttributeAction
{
    public function handle(
        UpdateCatalogAttributeRequest $request,
        CatalogAttribute $catalogAttribute,
    ): CatalogAttribute {
        $validated = $request->validated();

        $data = [
            'name' => $validated['name'],
            'type' => $validated['type'],
        ];

        if (array_key_exists('slug', $validated) && is_string($validated['slug']) && trim($validated['slug']) !== '') {
            $data['slug'] = $this->ensureUniqueSlug(Str::slug($validated['slug']), $catalogAttribute->id);
        } elseif ($validated['name'] !== $catalogAttribute->name) {
            $data['slug'] = $this->ensureUniqueSlug(Str::slug($validated['name']), $catalogAttribute->id);
        }

        if (array_key_exists('unit', $validated)) {
            $data['unit'] = $validated['unit'];
        }

        if (array_key_exists('is_filterable', $validated)) {
            $data['is_filterable'] = $validated['is_filterable'];
        }

        if (array_key_exists('is_required', $validated)) {
            $data['is_required'] = $validated['is_required'];
        }

        if (array_key_exists('sort_order', $validated)) {
            $data['sort_order'] = $validated['sort_order'];
        }

        if (array_key_exists('is_active', $validated)) {
            $data['is_active'] = $validated['is_active'];
        }

        $catalogAttribute->update($data);

        return $catalogAttribute->fresh(['options', 'catalogProductTypes']);
    }

    private function ensureUniqueSlug(string $original, string $ignoreId): string
    {
        $slug = $original !== '' ? $original : 'attribute';
        $candidate = $slug;
        $counter = 1;

        while (
            CatalogAttribute::query()
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
