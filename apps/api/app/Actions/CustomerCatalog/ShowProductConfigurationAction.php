<?php

namespace App\Actions\CustomerCatalog;

use App\Models\Product;
use App\Services\ProductConfiguration\ResolveStorefrontConfigurationOptions;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowProductConfigurationAction
{
    public function __construct(
        private readonly ResolveStorefrontConfigurationOptions $resolveOptions,
    ) {}

    /**
     * @param  array<string, string>  $selections
     * @return array<string, mixed>
     */
    public function handle(Product $product, array $selections = []): array
    {
        if ($product->is_demo || ! $product->isPurchasable()) {
            throw new NotFoundHttpException('Product not found.');
        }

        // Allowed options exclude paths that only match out-of-stock configurations.
        $result = $this->resolveOptions->handle($product, $selections, inStockOnly: true);
        $schema = $result['schema'];

        return [
            'product_id' => $product->id,
            'product_type' => $schema['product_type'] === null ? null : [
                'id' => $schema['product_type']->id,
                'name' => $schema['product_type']->name,
                'slug' => $schema['product_type']->slug,
                'sku_pattern' => $schema['product_type']->sku_pattern,
            ],
            'capabilities' => $schema['capabilities'],
            'attributes' => $schema['attributes'],
            'dependencies' => $schema['dependencies'],
            'configurations' => $result['configurations'],
            'allowed_value_ids' => $result['allowed_value_ids'],
            'matched_configuration_id' => $result['matched_configuration_id'],
            'is_complete' => $result['is_complete'],
            'is_in_stock' => $result['is_in_stock'],
            'has_configurations' => ($schema['capabilities']['has_configurations'] ?? false)
                && count($result['configurations']) > 0,
        ];
    }
}
