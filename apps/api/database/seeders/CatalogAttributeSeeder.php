<?php

namespace Database\Seeders;

use App\Enums\CatalogAttributeType;
use App\Enums\CatalogOrigin;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductType;
use Database\Support\CatalogAttributeDomainMap;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogAttributeSeeder extends Seeder
{
    /**
     * @return list<array{
     *     name: string,
     *     slug?: string,
     *     type: string,
     *     unit: ?string,
     *     is_filterable: bool,
     *     options?: list<string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            // Legacy / shared (stable slugs preserved)
            ['name' => 'Brand', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Generic', 'Premium', 'OEM']],
            ['name' => 'Color', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Black', 'White', 'Red', 'Blue', 'Green', 'Grey']],
            ['name' => 'Size', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
            ['name' => 'Material', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Cotton', 'Polyester', 'Leather', 'Metal', 'Plastic']],
            ['name' => 'RAM', 'type' => 'select', 'unit' => 'GB', 'is_filterable' => true, 'options' => ['4GB', '6GB', '8GB', '12GB', '16GB']],
            ['name' => 'Storage', 'type' => 'select', 'unit' => 'GB', 'is_filterable' => true, 'options' => ['64GB', '128GB', '256GB', '512GB', '1TB']],
            ['name' => 'Screen Size', 'type' => 'number', 'unit' => 'inch', 'is_filterable' => true],
            ['name' => 'Battery Capacity', 'type' => 'number', 'unit' => 'mAh', 'is_filterable' => true],
            ['name' => 'Camera', 'type' => 'text', 'unit' => 'MP', 'is_filterable' => false],
            ['name' => 'Power Output', 'type' => 'number', 'unit' => 'W', 'is_filterable' => true],
            ['name' => 'Speaker Size', 'type' => 'select', 'unit' => 'inch', 'is_filterable' => true, 'options' => ['8"', '10"', '12"', '15"', '18"']],
            ['name' => 'Channels', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['1', '2', '4', '8']],
            ['name' => 'Bluetooth', 'type' => 'boolean', 'unit' => null, 'is_filterable' => true],
            ['name' => 'Wireless', 'type' => 'boolean', 'unit' => null, 'is_filterable' => true],
            ['name' => 'Frequency Response', 'type' => 'text', 'unit' => 'Hz', 'is_filterable' => false],
            ['name' => 'Gender', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Men', 'Women', 'Unisex']],
            ['name' => 'Fabric', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Cotton', 'Linen', 'Silk', 'Denim', 'Polyester']],
            ['name' => 'Style', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Casual', 'Formal', 'Sport', 'Party', 'Office']],

            // Cross-domain specifications
            ['name' => 'Model', 'type' => 'text', 'unit' => null, 'is_filterable' => false],
            ['name' => 'Dimensions', 'type' => 'text', 'unit' => 'cm', 'is_filterable' => false],
            ['name' => 'Voltage', 'type' => 'select', 'unit' => 'V', 'is_filterable' => true, 'options' => ['110V', '220V', '110-240V']],
            ['name' => 'Power', 'type' => 'number', 'unit' => 'W', 'is_filterable' => true],
            ['name' => 'Capacity', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['1L', '2L', '5L', '10L', '20L', '50L', '100L', '200L']],
            ['name' => 'Energy Rating', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['A+++', 'A++', 'A+', 'A', 'B', 'C']],
            ['name' => 'Build Material', 'slug' => 'build-material', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Steel', 'Stainless Steel', 'Aluminum', 'Plastic', 'Wood', 'Glass', 'Rubber', 'Metal']],
            ['name' => 'Material Spec', 'slug' => 'material-spec', 'type' => 'text', 'unit' => null, 'is_filterable' => false],
            ['name' => 'Compatibility', 'type' => 'text', 'unit' => null, 'is_filterable' => false],
            ['name' => 'Weight', 'type' => 'number', 'unit' => 'kg', 'is_filterable' => true],

            // Furniture
            ['name' => 'Furniture Size', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Single', 'Double', 'Queen', 'King', 'Compact', 'Standard', 'Large']],
            ['name' => 'Mattress Size', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Single', 'Double', 'Queen', 'King']],
            ['name' => 'Seating Capacity', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['1', '2', '3', '4', '5', '6+']],

            // Beauty
            ['name' => 'Shade', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Fair', 'Light', 'Medium', 'Tan', 'Deep', 'Red', 'Nude', 'Pink', 'Brown', 'Black']],
            ['name' => 'Volume', 'type' => 'select', 'unit' => 'ml', 'is_filterable' => true, 'options' => ['15ml', '30ml', '50ml', '100ml', '200ml', '500ml']],
            ['name' => 'Skin Type', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Normal', 'Dry', 'Oily', 'Combination', 'Sensitive']],
            ['name' => 'Hair Type', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Straight', 'Wavy', 'Curly', 'Coily', 'All']],
            ['name' => 'Finish', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Matte', 'Glossy', 'Satin', 'Natural', 'Shimmer']],
            ['name' => 'Fragrance Family', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Floral', 'Woody', 'Fresh', 'Oriental', 'Citrus']],
            ['name' => 'Wig Length', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Short', 'Medium', 'Long', 'Extra Long']],
            ['name' => 'Wig Texture', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Straight', 'Body Wave', 'Curly', 'Kinky', 'Water Wave']],

            // Health
            ['name' => 'Measurement Range', 'type' => 'text', 'unit' => null, 'is_filterable' => false],
            ['name' => 'Accuracy', 'type' => 'text', 'unit' => null, 'is_filterable' => false],
            ['name' => 'Medical Size', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['XS', 'S', 'M', 'L', 'XL', 'Universal']],
            ['name' => 'Pack Quantity', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['1', '2', '5', '10', '20', '50', '100']],
            ['name' => 'Pack Label', 'type' => 'text', 'unit' => null, 'is_filterable' => false],

            // Jewelry
            ['name' => 'Metal', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Gold', 'Silver', 'Rose Gold', 'Stainless Steel', 'Platinum', 'Alloy']],
            ['name' => 'Ring Size', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['5', '6', '7', '8', '9', '10', '11', '12']],
            ['name' => 'Chain Length', 'type' => 'select', 'unit' => 'cm', 'is_filterable' => true, 'options' => ['40cm', '45cm', '50cm', '55cm', '60cm']],
            ['name' => 'Stone Type', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['None', 'Diamond', 'CZ', 'Pearl', 'Gemstone']],
            ['name' => 'Watch Movement', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Quartz', 'Automatic', 'Mechanical', 'Digital', 'Smart']],
            ['name' => 'Dial Size', 'type' => 'select', 'unit' => 'mm', 'is_filterable' => true, 'options' => ['36mm', '40mm', '42mm', '44mm', '46mm']],
            ['name' => 'Strap Material', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Leather', 'Metal', 'Silicone', 'Nylon', 'Ceramic']],
            ['name' => 'Strap Color', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Black', 'Brown', 'Silver', 'Gold', 'White', 'Blue']],

            // Sports
            ['name' => 'Shoe Size', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => [
                'EU 36', 'EU 37', 'EU 38', 'EU 39', 'EU 40', 'EU 41', 'EU 42', 'EU 43', 'EU 44', 'EU 45',
                'UK 3', 'UK 4', 'UK 5', 'UK 6', 'UK 7', 'UK 8', 'UK 9', 'UK 10', 'UK 11',
                'US 5', 'US 6', 'US 7', 'US 8', 'US 9', 'US 10', 'US 11', 'US 12',
            ]],

            // Automotive
            ['name' => 'Vehicle Make', 'type' => 'text', 'unit' => null, 'is_filterable' => true],
            ['name' => 'Vehicle Model', 'type' => 'text', 'unit' => null, 'is_filterable' => true],
            ['name' => 'Vehicle Year Range', 'type' => 'text', 'unit' => null, 'is_filterable' => true],
            ['name' => 'Fitment', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Universal', 'Vehicle Specific', 'Model Specific']],

            // Industrial
            ['name' => 'Speed RPM', 'slug' => 'speed-rpm', 'type' => 'number', 'unit' => 'RPM', 'is_filterable' => true],
            ['name' => 'Tool Size', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['6mm', '8mm', '10mm', '12mm', '1/4"', '3/8"', '1/2"', 'Set']],

            // Toys
            ['name' => 'Recommended Age', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['0-12 months', '1-3 years', '3-5 years', '5-8 years', '8+ years']],
            ['name' => 'Battery Requirement', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['None', 'AA', 'AAA', 'Rechargeable', 'USB']],
            ['name' => 'Kids Size', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['0-3M', '3-6M', '6-12M', '1-2Y', '2-3Y', '3-4Y', '4-5Y', '5-6Y', '6-8Y', '8-10Y']],

            // Pets
            ['name' => 'Pet Type', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Dog', 'Cat', 'Bird', 'Fish', 'Small Animal']],
            ['name' => 'Breed Size', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Toy', 'Small', 'Medium', 'Large', 'Giant']],
            ['name' => 'Pet Size', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['XS', 'S', 'M', 'L', 'XL']],

            // Groceries
            ['name' => 'Net Weight', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['100g', '250g', '500g', '1kg', '2kg', '5kg', '250ml', '500ml', '1L', '2L']],
            ['name' => 'Flavor', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['Original', 'Chocolate', 'Vanilla', 'Strawberry', 'Spicy', 'Salty', 'Sweet', 'Unflavored']],
            ['name' => 'Ingredients', 'type' => 'text', 'unit' => null, 'is_filterable' => false],
            ['name' => 'Country of Origin', 'type' => 'text', 'unit' => null, 'is_filterable' => true],
            ['name' => 'Dietary Type', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['None', 'Vegetarian', 'Vegan', 'Gluten Free', 'Halal', 'Organic']],
            ['name' => 'Dietary Type Label', 'slug' => 'dietary-type-label', 'type' => 'text', 'unit' => null, 'is_filterable' => false],

            // Professional audio extras
            ['name' => 'Connector Type', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['XLR', 'TRS', 'TS', 'Speakon', 'RCA', 'USB', 'Thunderbolt', '3.5mm']],
            ['name' => 'Cable Length', 'type' => 'select', 'unit' => 'm', 'is_filterable' => true, 'options' => ['1m', '2m', '3m', '5m', '10m', '15m', '20m']],
            ['name' => 'Impedance', 'type' => 'text', 'unit' => 'Ohm', 'is_filterable' => false],
            ['name' => 'Interface Type', 'type' => 'select', 'unit' => null, 'is_filterable' => true, 'options' => ['USB', 'USB-C', 'Thunderbolt', 'FireWire', 'PCIe']],
        ];
    }

    /**
     * @deprecated Replaced by CatalogAttributeDomainMap — kept empty so old callers fail closed.
     *
     * @return array<string, list<string>>
     */
    public static function groupTypeMatchers(): array
    {
        return [];
    }

    public function run(): void
    {
        $attributesBySlug = [];

        foreach (self::definitions() as $index => $definition) {
            $slug = $definition['slug'] ?? Str::slug($definition['name']);

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

            $attributesBySlug[$slug] = $attribute->id;
        }

        $this->assignToChinaCatalogProductTypes($attributesBySlug);
    }

    /**
     * @param  array<string, string>  $attributesBySlug
     */
    private function assignToChinaCatalogProductTypes(array $attributesBySlug): void
    {
        $types = CatalogProductType::query()
            ->with(['subcategory.department'])
            ->whereHas(
                'subcategory',
                fn ($query) => $query
                    ->where('origin', CatalogOrigin::China)
                    ->whereNotNull('department_id'),
            )
            ->get();

        foreach ($types as $type) {
            $slugs = CatalogAttributeDomainMap::attributeSlugsFor($type);
            $sync = [];

            foreach ($slugs as $index => $slug) {
                $attributeId = $attributesBySlug[$slug] ?? null;
                if ($attributeId === null) {
                    continue;
                }

                $sync[$attributeId] = [
                    'is_required' => false,
                    'sort_order' => $index + 1,
                ];
            }

            // Replace China mappings with the deterministic domain set (removes unsafe apparel axes).
            $type->attributes()->sync($sync);
        }
    }
}
