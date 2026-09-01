<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Product;
use Illuminate\Validation\Validator;

trait ValidatesPurchaseQuantityRules
{
    /**
     * @return array<string, list<string>>
     */
    protected function purchaseQuantityValidationRules(bool $sometimes = false): array
    {
        $prefix = $sometimes ? ['sometimes'] : [];

        return [
            'minimum_order_quantity' => [...$prefix, 'nullable', 'integer', 'min:1'],
            'order_increment' => [...$prefix, 'nullable', 'integer', 'min:1'],
        ];
    }

    protected function normalizePurchaseQuantityInput(): void
    {
        foreach (['minimum_order_quantity', 'order_increment'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);

            if ($value === '' || (is_string($value) && trim($value) === '')) {
                $this->merge([$field => null]);
            }
        }
    }

    protected function validatePurchaseQuantityCrossField(Validator $validator, ?Product $existing = null): void
    {
        $moqPresent = $this->exists('minimum_order_quantity');
        $incrementPresent = $this->exists('order_increment');

        if (! $moqPresent && ! $incrementPresent) {
            return;
        }

        $minimum = $moqPresent
            ? $this->input('minimum_order_quantity')
            : $existing?->minimum_order_quantity;
        $increment = $incrementPresent
            ? $this->input('order_increment')
            : $existing?->order_increment;

        if ($this->positiveIntOrNull($increment) !== null && $this->positiveIntOrNull($minimum) === null) {
            $validator->errors()->add(
                'order_increment',
                'An order increment requires a minimum order quantity.',
            );
        }
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $int = (int) $value;

            return $int >= 1 ? $int : null;
        }

        return null;
    }
}
