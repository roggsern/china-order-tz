<?php

namespace Database\Seeders;

use App\Enums\CatalogOrigin;
use App\Models\Category;
use App\Models\Department;
use Database\Support\CatalogBible;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds CatalogBible hierarchy plus department-linked starter categories.
 */
class CategorySeeder extends Seeder
{
    /**
     * Department-linked leaf categories that should remain active for admin catalog use.
     * Parents that have SubcategorySeeder children stay inactive (storefront-safe).
     *
     * @var list<string>
     */
    private const ACTIVE_DEPARTMENT_CATEGORY_SLUGS = [
        'computers-office-computer-accessories',
        'computers-office-desktop-computers',
        'computers-office-laptops',
        'computers-office-monitors',
        'computers-office-printers',
        'consumer-electronics-audio',
        'consumer-electronics-cameras',
        'consumer-electronics-gaming',
        'consumer-electronics-smart-devices',
        'consumer-electronics-tvs',
        'phones-tablets-feature-phones',
        'phones-tablets-tablets',
        'professional-audio-speakers',
        'professional-audio-audio-accessories',
        // Home Appliances — leaf roots (no children)
        'home-appliances-refrigerators-freezers',
        'home-appliances-washing-machines-dryers',
        'home-appliances-air-conditioners-fans',
        'home-appliances-water-heaters-dispensers',
        'home-appliances-vacuum-cleaners',
        'home-appliances-irons-garment-care',
        'home-appliances-home-appliance-parts',
        // Home & Furniture — all leaf roots
        'home-furniture-living-room-furniture',
        'home-furniture-bedroom-furniture',
        'home-furniture-dining-room-furniture',
        'home-furniture-office-furniture',
        'home-furniture-kitchen-furniture',
        'home-furniture-mattresses-bedding',
        'home-furniture-home-decor',
        'home-furniture-lighting',
        'home-furniture-storage-organization',
        'home-furniture-bathroom-accessories',
        'home-furniture-outdoor-furniture',
        // Beauty — leaf roots without children
        'beauty-personal-care-wigs-hair-extensions',
        'beauty-personal-care-makeup',
        'beauty-personal-care-fragrances',
        'beauty-personal-care-bath-body',
        'beauty-personal-care-nail-care',
        'beauty-personal-care-personal-care-appliances',
        'beauty-personal-care-mens-grooming',
        // Health & Medical — all leaf roots
        'health-medical-medical-equipment',
        'health-medical-health-monitoring-devices',
        'health-medical-mobility-aids',
        'health-medical-first-aid',
        'health-medical-personal-protective-equipment',
        'health-medical-dental-care',
        'health-medical-vision-care',
        'health-medical-rehabilitation-support',
        'health-medical-home-health-care',
        'health-medical-medical-supplies',
        // Jewelry — leaf roots without children
        'jewelry-watches-womens-jewelry',
        'jewelry-watches-mens-jewelry',
        'jewelry-watches-rings',
        'jewelry-watches-necklaces',
        'jewelry-watches-earrings',
        'jewelry-watches-bracelets',
        'jewelry-watches-jewelry-sets',
        'jewelry-watches-fashion-jewelry',
        'jewelry-watches-jewelry-storage-display',
        // Sports — leaf roots without children
        'sports-outdoors-outdoor-recreation',
        'sports-outdoors-cycling',
        'sports-outdoors-camping-hiking',
        'sports-outdoors-water-sports',
        'sports-outdoors-sportswear',
        'sports-outdoors-sports-shoes',
        'sports-outdoors-sports-equipment',
        'sports-outdoors-games-leisure',
        // Automotive — leaf roots without children
        'automotive-car-accessories',
        'automotive-motorcycle-accessories',
        'automotive-vehicle-parts',
        'automotive-tools-emergency-equipment',
        'automotive-interior-accessories',
        'automotive-exterior-accessories',
        'automotive-lighting',
        'automotive-tires-wheels',
        // Industrial & Tools — all leaf roots
        'industrial-tools-power-tools',
        'industrial-tools-hand-tools',
        'industrial-tools-measuring-tools',
        'industrial-tools-welding-equipment',
        'industrial-tools-safety-equipment',
        'industrial-tools-electrical-equipment',
        'industrial-tools-hardware',
        'industrial-tools-workshop-equipment',
        'industrial-tools-construction-tools',
        'industrial-tools-industrial-machinery',
        // Toys & Kids — leaf roots without children
        'toys-kids-educational-toys',
        'toys-kids-dolls-accessories',
        'toys-kids-remote-control-toys',
        'toys-kids-outdoor-toys',
        'toys-kids-building-toys',
        'toys-kids-pretend-play',
        'toys-kids-kids-furniture',
        'toys-kids-school-supplies',
        'toys-kids-kids-clothing-accessories',
        // Pet Supplies — all leaf roots
        'pet-supplies-dog-supplies',
        'pet-supplies-cat-supplies',
        'pet-supplies-pet-food',
        'pet-supplies-pet-grooming',
        'pet-supplies-pet-beds-furniture',
        'pet-supplies-pet-toys',
        'pet-supplies-pet-clothing',
        'pet-supplies-pet-health-hygiene',
        'pet-supplies-aquatic-supplies',
        'pet-supplies-bird-supplies',
        // Groceries — all leaf roots
        'groceries-snacks',
        'groceries-beverages',
        'groceries-tea-coffee',
        'groceries-breakfast-foods',
        'groceries-baking-supplies',
        'groceries-cooking-ingredients',
        'groceries-sauces-condiments',
        'groceries-canned-packaged-foods',
        'groceries-candy-chocolate',
        'groceries-food-storage',
        // Professional Audio — additional leaf roots
        'professional-audio-studio-equipment',
        'professional-audio-dj-equipment',
        'professional-audio-audio-interfaces',
        'professional-audio-recording-accessories',
        'professional-audio-cables-connectors',
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function departmentCategories(): array
    {
        return [
            'mens-fashion' => [
                'Clothing',
                'Shoes',
                'Bags',
                'Accessories',
            ],
            'womens-fashion' => [
                'Dresses',
                'Tops',
                'Skirts',
                'Pants',
                'Shoes',
                'Hand Bags',
                'Accessories',
            ],
            'phones-tablets' => [
                'Smartphones',
                'Feature Phones',
                'Tablets',
                'Phone Accessories',
                'Chargers',
                'Power Banks',
            ],
            'computers-office' => [
                'Laptops',
                'Desktop Computers',
                'Monitors',
                'Printers',
                'Computer Accessories',
            ],
            'consumer-electronics' => [
                'TVs',
                'Audio',
                'Cameras',
                'Smart Devices',
                'Gaming',
            ],
            'home-appliances' => [
                'Refrigerators & Freezers',
                'Washing Machines & Dryers',
                'Air Conditioners & Fans',
                'Cooking Appliances',
                'Kitchen Appliances',
                'Water Heaters & Dispensers',
                'Vacuum Cleaners',
                'Irons & Garment Care',
                'Home Appliance Parts',
            ],
            'home-furniture' => [
                'Living Room Furniture',
                'Bedroom Furniture',
                'Dining Room Furniture',
                'Office Furniture',
                'Kitchen Furniture',
                'Mattresses & Bedding',
                'Home Decor',
                'Lighting',
                'Storage & Organization',
                'Bathroom Accessories',
                'Outdoor Furniture',
            ],
            'beauty-personal-care' => [
                'Hair Care',
                'Wigs & Hair Extensions',
                'Skin Care',
                'Makeup',
                'Fragrances',
                'Bath & Body',
                'Nail Care',
                'Beauty Tools',
                'Personal Care Appliances',
                "Men's Grooming",
            ],
            'health-medical' => [
                'Medical Equipment',
                'Health Monitoring Devices',
                'Mobility Aids',
                'First Aid',
                'Personal Protective Equipment',
                'Dental Care',
                'Vision Care',
                'Rehabilitation & Support',
                'Home Health Care',
                'Medical Supplies',
            ],
            'jewelry-watches' => [
                "Women's Jewelry",
                "Men's Jewelry",
                'Watches',
                'Rings',
                'Necklaces',
                'Earrings',
                'Bracelets',
                'Jewelry Sets',
                'Fashion Jewelry',
                'Jewelry Storage & Display',
            ],
            'sports-outdoors' => [
                'Fitness & Exercise',
                'Team Sports',
                'Outdoor Recreation',
                'Cycling',
                'Camping & Hiking',
                'Water Sports',
                'Sportswear',
                'Sports Shoes',
                'Sports Equipment',
                'Games & Leisure',
            ],
            'automotive' => [
                'Car Electronics',
                'Car Accessories',
                'Motorcycle Accessories',
                'Vehicle Parts',
                'Car Care',
                'Tools & Emergency Equipment',
                'Interior Accessories',
                'Exterior Accessories',
                'Lighting',
                'Tires & Wheels',
            ],
            'industrial-tools' => [
                'Power Tools',
                'Hand Tools',
                'Measuring Tools',
                'Welding Equipment',
                'Safety Equipment',
                'Electrical Equipment',
                'Hardware',
                'Workshop Equipment',
                'Construction Tools',
                'Industrial Machinery',
            ],
            'toys-kids' => [
                'Baby Products',
                'Educational Toys',
                'Dolls & Accessories',
                'Remote Control Toys',
                'Outdoor Toys',
                'Building Toys',
                'Pretend Play',
                "Kids' Furniture",
                'School Supplies',
                "Kids' Clothing Accessories",
            ],
            'pet-supplies' => [
                'Dog Supplies',
                'Cat Supplies',
                'Pet Food',
                'Pet Grooming',
                'Pet Beds & Furniture',
                'Pet Toys',
                'Pet Clothing',
                'Pet Health & Hygiene',
                'Aquatic Supplies',
                'Bird Supplies',
            ],
            'groceries' => [
                'Snacks',
                'Beverages',
                'Tea & Coffee',
                'Breakfast Foods',
                'Baking Supplies',
                'Cooking Ingredients',
                'Sauces & Condiments',
                'Canned & Packaged Foods',
                'Candy & Chocolate',
                'Food Storage',
            ],
            'professional-audio' => [
                'PA Systems',
                'Mixers',
                'Amplifiers',
                'Speakers',
                'Microphones',
                'Audio Accessories',
                'Studio Equipment',
                'DJ Equipment',
                'Audio Interfaces',
                'Recording Accessories',
                'Cables & Connectors',
            ],
        ];
    }

    public function run(): void
    {
        foreach (CatalogBible::categories() as $rootDefinition) {
            $root = Category::query()->updateOrCreate(
                ['slug' => $rootDefinition['slug']],
                [
                    'parent_id' => null,
                    'department_id' => null,
                    'store_id' => null,
                    'origin' => CatalogOrigin::from($rootDefinition['origin']),
                    'name' => $rootDefinition['name'],
                    'sort_order' => $rootDefinition['sort_order'],
                    'is_active' => true,
                ],
            );

            foreach ($rootDefinition['children'] ?? [] as $childDefinition) {
                Category::query()->updateOrCreate(
                    ['slug' => $childDefinition['slug']],
                    [
                        'parent_id' => $root->id,
                        'department_id' => null,
                        'store_id' => null,
                        'origin' => $root->origin,
                        'name' => $childDefinition['name'],
                        'sort_order' => $childDefinition['sort_order'],
                        'is_active' => true,
                    ],
                );
            }
        }

        $this->seedDepartmentCategories();
    }

    private function seedDepartmentCategories(): void
    {
        // Department starter categories are admin/internal helpers.
        // They must NOT become ORDER FROM CHINA mega-menu roots (that uses CatalogBible only).
        // Keep them inactive for storefront navigation while remaining available for admin tooling.
        foreach (self::departmentCategories() as $departmentSlug => $categoryNames) {
            $department = Department::query()->where('slug', $departmentSlug)->first();

            if ($department === null) {
                continue;
            }

            foreach ($categoryNames as $index => $name) {
                $slug = $departmentSlug.'-'.Str::slug($name);

                Category::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'department_id' => $department->id,
                        'parent_id' => null,
                        'origin' => CatalogOrigin::China,
                        'name' => $name,
                        'image' => null,
                        'description' => null,
                        'sort_order' => $index + 1,
                        'is_active' => in_array($slug, self::ACTIVE_DEPARTMENT_CATEGORY_SLUGS, true),
                    ],
                );
            }
        }
    }
}
