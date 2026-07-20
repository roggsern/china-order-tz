<?php

namespace Database\Seeders;

use App\Enums\CatalogAttributeType;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogAttributeSeeder extends Seeder
{
    /**
     * @return list<array{name: string, type: string, unit: ?string, is_filterable: bool, options?: list<string>, groups: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            // GENERAL
            ['name' => 'Brand', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Generic', 'Premium', 'OEM'], 'groups' => ['general', 'phones', 'pa', 'fashion']],
            ['name' => 'Color', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Black', 'White', 'Red', 'Blue', 'Green', 'Grey'], 'groups' => ['general', 'fashion', 'phones']],
            ['name' => 'Size', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'], 'groups' => ['general', 'fashion']],
            ['name' => 'Material', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Cotton', 'Polyester', 'Leather', 'Metal', 'Plastic'], 'groups' => ['general', 'fashion']],

            // PHONES
            ['name' => 'RAM', 'type' => 'select', 'unit' => 'GB', 'is_filterable' => true, 'options' => ['4GB', '6GB', '8GB', '12GB', '16GB'], 'groups' => ['phones']],
            ['name' => 'Storage', 'type' => 'select', 'unit' => 'GB', 'is_filterable' => true, 'options' => ['64GB', '128GB', '256GB', '512GB', '1TB'], 'groups' => ['phones']],
            ['name' => 'Screen Size', 'type' => 'number', 'unit' => 'inch', 'is_filterable' => true, 'groups' => ['phones']],
            ['name' => 'Battery Capacity', 'type' => 'number', 'unit' => 'mAh', 'is_filterable' => true, 'groups' => ['phones']],
            ['name' => 'Camera', 'type' => 'text', 'unit' => 'MP', 'is_filterable' => false, 'groups' => ['phones']],

            // PA SYSTEM
            ['name' => 'Power Output', 'type' => 'number', 'unit' => 'W', 'is_filterable' => true, 'groups' => ['pa']],
            ['name' => 'Speaker Size', 'type' => 'select', 'unit' => 'inch', 'is_filterable' => true, 'options' => ['8"', '10"', '12"', '15"', '18"'], 'groups' => ['pa']],
            ['name' => 'Channels', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['1', '2', '4', '8'], 'groups' => ['pa']],
            ['name' => 'Bluetooth', 'type' => 'boolean', 'unit' => null, 'is_filterable' => true, 'groups' => ['pa']],
            ['name' => 'Wireless', 'type' => 'boolean', 'unit' => null, 'is_filterable' => true, 'groups' => ['pa']],
            ['name' => 'Frequency Response', 'type' => 'text', 'unit' => 'Hz', 'is_filterable' => false, 'groups' => ['pa']],

            // FASHION
            ['name' => 'Gender', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Men', 'Women', 'Unisex'], 'groups' => ['fashion']],
            ['name' => 'Fabric', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Cotton', 'Linen', 'Silk', 'Denim', 'Polyester'], 'groups' => ['fashion']],
            ['name' => 'Style', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Casual', 'Formal', 'Sport', 'Party', 'Office'], 'groups' => ['fashion']],
        ];
    }

    /**
     * Map attribute groups → catalog product type name fragments.
     *
     * @return array<string, list<string>>
     */
    public static function groupTypeMatchers(): array
    {
        return [
            'phones' => ['Smartphone', 'iPhone', 'Phone', 'Android'],
            'pa' => ['PA System', 'PA Speaker', 'Sound System', 'Line Array'],
            'fashion' => ['T-Shirt', 'Shirt', 'Dress', 'Sneakers', 'Shoes', 'Heels', 'Flats', 'Sandals', 'Boots'],
            'general' => [],
        ];
    }

    public function run(): void
    {
        $attributesByGroup = [];

        foreach (self::definitions() as $index => $definition) {
            $slug = Str::slug($definition['name']);

            $attribute = CatalogAttribute::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'type' => CatalogAttributeType::from($definition['type']),
                    'unit' => $definition['unit'],
                    'is_filterable' => $definition['is_filterable'],
                    'is_required' => false,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );

            if (! empty($definition['options'])) {
                foreach ($definition['options'] as $optionIndex => $value) {
                    CatalogAttributeOption::query()->updateOrCreate(
                        [
                            'catalog_attribute_id' => $attribute->id,
                            'slug' => Str::slug($value),
                        ],
                        [
                            'value' => $value,
                            'sort_order' => $optionIndex + 1,
                        ],
                    );
                }
            }

            foreach ($definition['groups'] as $group) {
                $attributesByGroup[$group][] = $attribute->id;
            }
        }

        $this->assignToCatalogProductTypes($attributesByGroup);
    }

    /**
     * @param  array<string, list<string>>  $attributesByGroup
     */
    private function assignToCatalogProductTypes(array $attributesByGroup): void
    {
        $matchers = self::groupTypeMatchers();
        $types = CatalogProductType::query()->get();

        foreach ($types as $type) {
            $attributeIds = $attributesByGroup['general'] ?? [];

            foreach ($matchers as $group => $needles) {
                if ($group === 'general' || empty($needles)) {
                    continue;
                }

                foreach ($needles as $needle) {
                    if (stripos($type->name, $needle) !== false) {
                        $attributeIds = array_merge($attributeIds, $attributesByGroup[$group] ?? []);
                        break;
                    }
                }
            }

            $attributeIds = array_values(array_unique($attributeIds));
            $sync = [];
            foreach ($attributeIds as $index => $attributeId) {
                $sync[$attributeId] = [
                    'is_required' => false,
                    'sort_order' => $index + 1,
                ];
            }

            if ($sync !== []) {
                $type->attributes()->syncWithoutDetaching($sync);
            }
        }
    }
}
