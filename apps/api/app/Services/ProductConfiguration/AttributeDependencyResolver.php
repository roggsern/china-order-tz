<?php

namespace App\Services\ProductConfiguration;

use App\Models\AttributeDependency;
use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Support\Collection;

/**
 * Attribute Dependency Engine.
 *
 * Rules are directed metadata: source value → allowed target values.
 * Sparse rules: a source value with no rows is unconstrained.
 *
 * Phase B uses directed validity for configuration generation.
 * Full bi-directional picker cascading lands with storefront (Phase D),
 * using the same rule rows plus full attribute value lists.
 */
class AttributeDependencyResolver
{
    /**
     * Forward cascade for pickers: given selections, which target values remain allowed.
     *
     * @param  array<string, string>  $selections  attribute_id => attribute_value_id
     * @return array<string, list<string>>  attribute_id => allowed attribute_value_ids when constrained
     */
    public function allowedValues(
        ProductType $productType,
        array $selections = [],
        ?Product $product = null,
    ): array {
        $dependencies = $this->loadDependencies($productType, $product);

        if ($dependencies->isEmpty()) {
            return [];
        }

        $allowed = [];

        foreach ($dependencies as $dependency) {
            $sourceId = $dependency->source_attribute_id;
            $targetId = $dependency->target_attribute_id;
            $sourceSelection = $selections[$sourceId] ?? null;

            if ($sourceSelection === null) {
                continue;
            }

            $matchedTargets = $dependency->rules
                ->where('source_attribute_value_id', $sourceSelection)
                ->pluck('target_attribute_value_id')
                ->unique()
                ->values()
                ->all();

            // Sparse: no rows for this source value → unconstrained.
            if ($matchedTargets === []) {
                continue;
            }

            $this->intersectAllowed($allowed, $targetId, $matchedTargets);
        }

        return $allowed;
    }

    /**
     * Directed validity for a complete attribute selection.
     *
     * @param  array<string, string>  $selections
     */
    public function isValidCombination(
        ProductType $productType,
        array $selections,
        ?Product $product = null,
    ): bool {
        $dependencies = $this->loadDependencies($productType, $product);

        foreach ($dependencies as $dependency) {
            $sourceSelection = $selections[$dependency->source_attribute_id] ?? null;
            $targetSelection = $selections[$dependency->target_attribute_id] ?? null;

            if ($sourceSelection === null || $targetSelection === null) {
                continue;
            }

            $rulesForSource = $dependency->rules
                ->where('source_attribute_value_id', $sourceSelection);

            if ($rulesForSource->isEmpty()) {
                continue;
            }

            $allowedTargets = $rulesForSource
                ->pluck('target_attribute_value_id')
                ->unique()
                ->all();

            if (! in_array($targetSelection, $allowedTargets, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function graph(ProductType $productType, ?Product $product = null): array
    {
        return $this->loadDependencies($productType, $product)
            ->map(function (AttributeDependency $dependency) {
                return [
                    'id' => $dependency->id,
                    'source_attribute_id' => $dependency->source_attribute_id,
                    'target_attribute_id' => $dependency->target_attribute_id,
                    'rules' => $dependency->rules->map(fn ($rule) => [
                        'id' => $rule->id,
                        'source_attribute_value_id' => $rule->source_attribute_value_id,
                        'target_attribute_value_id' => $rule->target_attribute_value_id,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, AttributeDependency>
     */
    private function loadDependencies(ProductType $productType, ?Product $product): Collection
    {
        $typeDeps = AttributeDependency::query()
            ->where('product_type_id', $productType->id)
            ->whereNull('product_id')
            ->with('rules')
            ->get();

        if ($product === null) {
            return $typeDeps;
        }

        $productDeps = AttributeDependency::query()
            ->where('product_id', $product->id)
            ->with('rules')
            ->get();

        $overrides = $productDeps
            ->mapWithKeys(fn (AttributeDependency $d) => [
                $d->source_attribute_id.'|'.$d->target_attribute_id => $d,
            ]);

        return $typeDeps
            ->reject(fn (AttributeDependency $d) => $overrides->has(
                $d->source_attribute_id.'|'.$d->target_attribute_id
            ))
            ->concat($productDeps)
            ->values();
    }

    /**
     * @param  array<string, list<string>>  $allowed
     * @param  list<string>  $valueIds
     */
    private function intersectAllowed(array &$allowed, string $attributeId, array $valueIds): void
    {
        if (! isset($allowed[$attributeId])) {
            $allowed[$attributeId] = $valueIds;

            return;
        }

        $allowed[$attributeId] = array_values(array_intersect(
            $allowed[$attributeId],
            $valueIds,
        ));
    }
}
