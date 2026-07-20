<?php

namespace App\Actions\AdminCatalogAttributes;

use App\Http\Requests\Admin\UpdateCatalogAttributeOptionRequest;
use App\Models\CatalogAttributeOption;
use Illuminate\Support\Str;

class UpdateCatalogAttributeOptionAction
{
    public function handle(
        UpdateCatalogAttributeOptionRequest $request,
        CatalogAttributeOption $catalogAttributeOption,
    ): CatalogAttributeOption {
        $validated = $request->validated();

        $data = ['value' => $validated['value']];

        if (array_key_exists('slug', $validated) && is_string($validated['slug']) && trim($validated['slug']) !== '') {
            $data['slug'] = $this->uniqueSlug(
                $catalogAttributeOption->catalog_attribute_id,
                Str::slug($validated['slug']),
                $catalogAttributeOption->id,
            );
        } elseif ($validated['value'] !== $catalogAttributeOption->value) {
            $data['slug'] = $this->uniqueSlug(
                $catalogAttributeOption->catalog_attribute_id,
                Str::slug($validated['value']),
                $catalogAttributeOption->id,
            );
        }

        if (array_key_exists('sort_order', $validated)) {
            $data['sort_order'] = $validated['sort_order'];
        }

        $catalogAttributeOption->update($data);

        return $catalogAttributeOption->fresh();
    }

    private function uniqueSlug(string $attributeId, string $slug, string $ignoreId): string
    {
        $original = $slug !== '' ? $slug : 'option';
        $candidate = $original;
        $counter = 1;

        while (
            CatalogAttributeOption::query()
                ->where('catalog_attribute_id', $attributeId)
                ->where('slug', $candidate)
                ->where('id', '!=', $ignoreId)
                ->exists()
        ) {
            $candidate = $original.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
