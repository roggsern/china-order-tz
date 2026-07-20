<?php

namespace App\Actions\AdminCatalogAttributes;

use App\Http\Requests\Admin\StoreCatalogAttributeOptionRequest;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateCatalogAttributeOptionAction
{
    public function handle(
        StoreCatalogAttributeOptionRequest $request,
        CatalogAttribute $catalogAttribute,
    ): CatalogAttributeOption {
        if (! $catalogAttribute->type?->requiresOptions()) {
            throw ValidationException::withMessages([
                'attribute' => ['Options are only allowed for select/multiselect attributes.'],
            ]);
        }

        $validated = $request->validated();
        $slugSource = isset($validated['slug']) && is_string($validated['slug']) && trim($validated['slug']) !== ''
            ? $validated['slug']
            : $validated['value'];

        return CatalogAttributeOption::create([
            'catalog_attribute_id' => $catalogAttribute->id,
            'value' => $validated['value'],
            'slug' => $this->uniqueSlug($catalogAttribute->id, Str::slug($slugSource)),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);
    }

    private function uniqueSlug(string $attributeId, string $slug): string
    {
        $original = $slug !== '' ? $slug : 'option';
        $candidate = $original;
        $counter = 1;

        while (
            CatalogAttributeOption::query()
                ->where('catalog_attribute_id', $attributeId)
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = $original.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
