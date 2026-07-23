<?php

namespace App\Services\ProductConfiguration;

use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use Illuminate\Support\Str;

/**
 * Builds configuration SKUs from Product Type sku_pattern metadata.
 * Pattern tokens: {ATTR:slug} replaced with the attribute value slug.
 */
class GenerateConfigurationSku
{
    /**
     * @param  array<string, string>  $selections  attribute_id => attribute_value_id
     */
    public function handle(
        ProductType $productType,
        string $baseSku,
        array $selections,
        int $sequence = 1,
    ): string {
        $pattern = $productType->sku_pattern;

        if (! filled($pattern)) {
            return $this->fallbackSku($baseSku, $sequence);
        }

        SkuPatternRules::assertValid($pattern);

        $values = ProductAttributeValue::query()
            ->with('attribute')
            ->whereIn('id', array_values($selections))
            ->get()
            ->keyBy(fn (ProductAttributeValue $value) => $value->attribute?->slug);

        $sku = preg_replace_callback(
            '/\{ATTR:([a-z0-9\-]+)\}/i',
            function (array $matches) use ($values) {
                $slug = Str::lower($matches[1]);
                $value = $values->get($slug);

                return $value ? Str::upper(Str::slug($value->slug ?: $value->value, '')) : 'X';
            },
            $pattern
        );

        $sku = Str::upper(preg_replace('/[^A-Z0-9\-_]/', '', (string) $sku) ?? '');

        if ($sku === '' || $sku === (string) $pattern) {
            return $this->fallbackSku($baseSku, $sequence);
        }

        // Prefix with product base SKU for uniqueness across products.
        $prefix = Str::upper(preg_replace('/[^A-Z0-9\-_]/', '', $baseSku) ?: 'SKU');

        return $prefix.'-'.$sku;
    }

    private function fallbackSku(string $baseSku, int $sequence): string
    {
        $prefix = Str::upper(preg_replace('/[^A-Z0-9\-_]/', '', $baseSku) ?: 'SKU');

        return sprintf('%s-CFG%03d', $prefix, $sequence);
    }
}
