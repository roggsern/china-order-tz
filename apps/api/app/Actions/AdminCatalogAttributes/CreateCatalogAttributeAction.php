<?php

namespace App\Actions\AdminCatalogAttributes;

use App\Enums\CatalogAttributeType;
use App\Http\Requests\Admin\StoreCatalogAttributeRequest;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use Illuminate\Support\Str;

class CreateCatalogAttributeAction
{
    public function handle(StoreCatalogAttributeRequest $request): CatalogAttribute
    {
        $validated = $request->validated();

        $slugSource = isset($validated['slug']) && is_string($validated['slug']) && trim($validated['slug']) !== ''
            ? $validated['slug']
            : $validated['name'];

        $attribute = CatalogAttribute::create([
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($slugSource),
            'type' => $validated['type'],
            'unit' => $validated['unit'] ?? null,
            'is_filterable' => $validated['is_filterable'] ?? false,
            'is_required' => $validated['is_required'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $type = $attribute->type instanceof CatalogAttributeType
            ? $attribute->type
            : CatalogAttributeType::tryFrom((string) $attribute->type);

        if ($type?->requiresOptions() && ! empty($validated['options'])) {
            foreach (array_values($validated['options']) as $index => $option) {
                $value = $option['value'];
                $optionSlug = isset($option['slug']) && is_string($option['slug']) && trim($option['slug']) !== ''
                    ? Str::slug($option['slug'])
                    : Str::slug($value);

                CatalogAttributeOption::create([
                    'catalog_attribute_id' => $attribute->id,
                    'value' => $value,
                    'slug' => $this->uniqueOptionSlug($attribute->id, $optionSlug),
                    'sort_order' => $option['sort_order'] ?? ($index + 1),
                ]);
            }
        }

        return $attribute->fresh(['options']);
    }

    private function generateUniqueSlug(string $value): string
    {
        $slug = Str::slug($value);
        $original = $slug !== '' ? $slug : 'attribute';
        $candidate = $original;
        $counter = 1;

        while (CatalogAttribute::where('slug', $candidate)->exists()) {
            $candidate = $original.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function uniqueOptionSlug(string $attributeId, string $slug): string
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
