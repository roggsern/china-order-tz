<?php

namespace Database\Seeders;

use App\Enums\CatalogAttributeType;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductAttributeValue;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogProductAttributeValueSeeder extends Seeder
{
    public function run(): void
    {
        Product::query()
            ->with(['catalogProductType.attributes.options'])
            ->whereNotNull('catalog_product_type_id')
            ->get()
            ->each(function (Product $product) {
                $assigned = $product->catalogProductType?->attributes;
                if ($assigned === null || $assigned->isEmpty()) {
                    return;
                }

                foreach ($assigned as $attribute) {
                    $slug = $attribute->slug;
                    $definition = $this->valueFor($product, $slug, $attribute);
                    if ($definition === null) {
                        continue;
                    }

                    CatalogProductAttributeValue::query()
                        ->where('product_id', $product->id)
                        ->where('catalog_attribute_id', $attribute->id)
                        ->forceDelete();

                    if (($definition['type'] ?? null) === 'multiselect') {
                        foreach ($definition['option_ids'] as $optionId) {
                            $option = CatalogAttributeOption::query()->find($optionId);
                            CatalogProductAttributeValue::query()->create([
                                'product_id' => $product->id,
                                'catalog_attribute_id' => $attribute->id,
                                'option_id' => $optionId,
                                'value_text' => $option?->value,
                                'is_active' => true,
                            ]);
                        }
                        continue;
                    }

                    CatalogProductAttributeValue::query()->create([
                        'product_id' => $product->id,
                        'catalog_attribute_id' => $attribute->id,
                        'value_text' => $definition['value_text'] ?? null,
                        'value_number' => $definition['value_number'] ?? null,
                        'value_boolean' => $definition['value_boolean'] ?? null,
                        'option_id' => $definition['option_id'] ?? null,
                        'is_active' => true,
                    ]);
                }
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function valueFor(Product $product, string $slug, CatalogAttribute $attribute): ?array
    {
        $name = Str::lower($product->name);
        $type = $attribute->type instanceof CatalogAttributeType
            ? $attribute->type
            : CatalogAttributeType::tryFrom((string) $attribute->type);

        return match ($slug) {
            'ram' => $this->optionValue($attribute, $this->pick($name, [
                'galaxy' => '12GB',
                'iphone' => '8GB',
                'pixel' => '12GB',
                'default' => '8GB',
            ])),
            'storage' => $this->optionValue($attribute, $this->pick($name, [
                'galaxy' => '256GB',
                'iphone' => '256GB',
                'default' => '128GB',
            ])),
            'battery-capacity' => [
                'value_number' => $this->pick($name, [
                    'galaxy' => 5000,
                    'iphone' => 4000,
                    'default' => 5000,
                ]),
                'value_text' => null,
            ],
            'screen-size' => [
                'value_number' => $this->pick($name, [
                    'galaxy' => 6.8,
                    'iphone' => 6.3,
                    'default' => 6.5,
                ]),
            ],
            'camera' => ['value_text' => '50MP'],
            'power-output' => [
                'value_number' => $this->pick($name, [
                    'jbl' => 1000,
                    'qsc' => 2000,
                    'default' => 500,
                ]),
            ],
            'channels' => $this->optionValue($attribute, $this->pick($name, [
                'mixer' => '8',
                'default' => '2',
            ])),
            'speaker-size' => $this->optionValue($attribute, '12"'),
            'bluetooth' => ['value_boolean' => true],
            'wireless' => ['value_boolean' => str_contains($name, 'wireless') || str_contains($name, 's1')],
            'frequency-response' => ['value_text' => '50Hz – 20kHz'],
            'color' => $this->optionValue($attribute, $this->pick($name, [
                'nike' => 'Black',
                'adidas' => 'White',
                'zara' => 'Red',
                'default' => 'Black',
            ])),
            'size' => $this->optionValue($attribute, $this->pick($name, [
                'dress' => 'M',
                'jeans' => 'L',
                'default' => 'M',
            ])),
            'material', 'fabric' => $this->optionValue($attribute, $this->pick($name, [
                'jeans' => 'Denim',
                'dress' => 'Cotton',
                'default' => 'Cotton',
            ])),
            'style' => $this->optionValue($attribute, $this->pick($name, [
                'dress' => 'Casual',
                'polo' => 'Sport',
                'default' => 'Casual',
            ])),
            'gender' => $this->optionValue($attribute, $this->pick($name, [
                'dress' => 'Women',
                'default' => 'Unisex',
            ])),
            'brand' => null, // catalog Brand attribute (select OEM/Premium) — skip; product.brand_id is source of truth
            default => $type === CatalogAttributeType::Boolean
                ? ['value_boolean' => false]
                : null,
        };
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function pick(string $name, array $map): mixed
    {
        foreach ($map as $needle => $value) {
            if ($needle === 'default') {
                continue;
            }
            if (str_contains($name, $needle)) {
                return $value;
            }
        }

        return $map['default'] ?? null;
    }

    /**
     * @return array{option_id: string, value_text: string}|null
     */
    private function optionValue(CatalogAttribute $attribute, ?string $label): ?array
    {
        if ($label === null) {
            return null;
        }

        $option = $attribute->options->first(
            fn (CatalogAttributeOption $option) => strcasecmp($option->value, $label) === 0
                || strcasecmp($option->slug, Str::slug($label)) === 0,
        );

        if ($option === null) {
            $option = $attribute->options->first();
        }

        if ($option === null) {
            return null;
        }

        return [
            'option_id' => $option->id,
            'value_text' => $option->value,
        ];
    }
}
