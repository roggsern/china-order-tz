<?php

namespace App\Services\Catalog;

use App\Actions\AdminProductAttributes\GetProductCatalogAttributesAction;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * Maps catalog attribute values into customer PDP specifications using the same
 * display formatting as admin product attributes.
 */
final class CustomerProductSpecificationsPresenter
{
    public function __construct(
        private readonly GetProductCatalogAttributesAction $getProductCatalogAttributes,
    ) {}

    /**
     * @return list<array{label: string, value: string}>
     */
    public function present(Product $product): array
    {
        if ($product->catalog_product_type_id === null) {
            return [];
        }

        try {
            $attributes = $this->getProductCatalogAttributes->handle($product);
        } catch (ValidationException) {
            return [];
        }

        $specifications = [];

        foreach ($attributes as $attribute) {
            $display = $attribute['value']['display'] ?? null;

            if ($display === null || $display === '') {
                continue;
            }

            $specifications[] = [
                'label' => (string) $attribute['name'],
                'value' => (string) $display,
            ];
        }

        return $specifications;
    }
}
