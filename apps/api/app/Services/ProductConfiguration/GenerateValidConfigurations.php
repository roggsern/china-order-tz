<?php

namespace App\Services\ProductConfiguration;

use App\Models\ProductType;
use Illuminate\Support\Collection;

/**
 * Builds valid sellable configuration combinations from type metadata.
 * Never hardcodes attribute meaning — uses type attributes + dependency rules.
 */
class GenerateValidConfigurations
{
    public function __construct(
        private readonly AttributeDependencyResolver $dependencyResolver,
    ) {}

    /**
     * @param  array<string, list<string>>  $selectedValueIdsByAttribute  attribute_id => [value_id, ...]
     * @return list<array{attribute_value_ids: list<string>, selections: array<string, string>}>
     */
    public function handle(ProductType $productType, array $selectedValueIdsByAttribute): array
    {
        $productType->loadMissing([
            'typeAttributes' => fn ($q) => $q
                ->where('participates_in_configuration', true)
                ->orderBy('sort_order'),
            'typeAttributes.attribute.values',
        ]);

        $axes = $productType->typeAttributes
            ->filter(fn ($ta) => $ta->participates_in_configuration)
            ->map(function ($typeAttribute) use ($selectedValueIdsByAttribute) {
                $attributeId = $typeAttribute->product_attribute_id;
                $allValueIds = $typeAttribute->attribute?->values
                    ->pluck('id')
                    ->values()
                    ->all() ?? [];

                $selected = $selectedValueIdsByAttribute[$attributeId] ?? $allValueIds;
                $selected = array_values(array_intersect($selected, $allValueIds));

                return [
                    'attribute_id' => $attributeId,
                    'value_ids' => $selected,
                ];
            })
            ->filter(fn (array $axis) => $axis['value_ids'] !== [])
            ->values();

        if ($axes->isEmpty()) {
            return [];
        }

        $combos = $this->cartesian($axes);

        return collect($combos)
            ->filter(fn (array $selections) => $this->isValid($productType, $selections))
            ->map(fn (array $selections) => [
                'attribute_value_ids' => array_values($selections),
                'selections' => $selections,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $selections  attribute_id => value_id
     */
    public function isValid(ProductType $productType, array $selections): bool
    {
        return $this->dependencyResolver->isValidCombination($productType, $selections);
    }

    /**
     * @param  Collection<int, array{attribute_id: string, value_ids: list<string>}>  $axes
     * @return list<array<string, string>>
     */
    private function cartesian(Collection $axes): array
    {
        $result = [[]];

        foreach ($axes as $axis) {
            $next = [];

            foreach ($result as $partial) {
                foreach ($axis['value_ids'] as $valueId) {
                    $partial[$axis['attribute_id']] = $valueId;
                    $next[] = $partial;
                }
            }

            $result = $next;
        }

        return $result;
    }
}
