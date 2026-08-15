<?php

namespace Database\Support;

use App\Enums\CatalogOrigin;
use App\Models\CatalogProductType;

/**
 * Deterministic CHINA_IMPORT catalog attribute assignment.
 *
 * Maps by department slug + exact product-type name allowlists.
 * Never uses broad substring matching (avoids Phone/Dress false positives).
 */
final class CatalogAttributeDomainMap
{
    /**
     * Select attributes treated as meaningful commerce variant axes.
     * Brand is intentionally excluded (specification convenience select).
     *
     * @var list<string>
     */
    public const VARIANT_ATTRIBUTE_SLUGS = [
        'color',
        'size',
        'capacity',
        'voltage',
        'shade',
        'volume',
        'wig-length',
        'wig-texture',
        'pack-quantity',
        'medical-size',
        'ring-size',
        'chain-length',
        'metal',
        'strap-color',
        'shoe-size',
        'furniture-size',
        'mattress-size',
        'fitment',
        'tool-size',
        'kids-size',
        'pet-size',
        'flavor',
        'net-weight',
        'cable-length',
        'connector-type',
        'ram',
        'storage',
        'speaker-size',
        'channels',
    ];

    /**
     * Exact catalog product type names eligible for product-level Product Condition.
     * Never attached as a catalog/variant attribute.
     *
     * @var list<string>
     */
    public const PRODUCT_CONDITION_ELIGIBLE_TYPE_NAMES = [
        // Phones / tablets
        'Android Smartphone',
        'iPhone',
        'Foldable Phone',
        'Gaming Phone',
        'Feature Phone',
        'Android Tablet',
        'iPad',
        // Computers
        'Gaming Laptop',
        'Business Laptop',
        'Ultrabook',
        'Tower Desktop',
        'All-in-One PC',
        'Mini PC',
        'Office Monitor',
        'Gaming Monitor',
        'Professional Monitor',
        // Consumer electronics
        'LED TV',
        'OLED TV',
        'Smart TV',
        'Bluetooth Speaker',
        'Soundbar',
        'Wireless Headphones',
        'DSLR Camera',
        'Mirrorless Camera',
        'Action Camera',
        'Smart Watch',
        'Fitness Smart Watch',
        'Smart Speaker',
        'Smart Home Hub',
        'Gaming Console',
        'Gaming Controller',
        'Gaming Headset',
        // Professional audio
        'Portable PA System',
        'Complete Sound System',
        'Active PA Speaker',
        'Passive PA Speaker',
        'Line Array System',
        'Analog Mixer',
        'Digital Mixer',
        'DJ Mixer',
        'Power Amplifier',
        'Integrated Amplifier',
        'Passive Speaker',
        'Studio Monitor',
        'Ceiling Speaker',
        'Studio Monitor Speaker',
        'DJ Controller',
        'DJ Turntable',
        'USB Audio Interface',
        'Thunderbolt Audio Interface',
        'Audio Interface',
        // Home appliances
        'Refrigerator',
        'Chest Freezer',
        'Mini Fridge',
        'Front Load Washing Machine',
        'Top Load Washing Machine',
        'Clothes Dryer',
        'Split Air Conditioner',
        'Portable Air Conditioner',
        'Electric Cooker',
        'Gas Cooker',
        'Microwave Oven',
        'Electric Oven',
        'Countertop Microwave',
        'Grill Microwave',
        'Electric Hot Plate',
        'Induction Cooker',
        'Air Fryer',
        'Air Fryer Oven',
        'Electric Water Heater',
        'Instant Water Heater',
        'Water Dispenser',
        'Canister Vacuum Cleaner',
        'Robot Vacuum Cleaner',
        'Handheld Vacuum Cleaner',
        // Automotive / vehicle electronics & parts
        'Jump Starter',
        'OBD Scanner',
        'Dash Camera',
        'Dual Channel Dash Camera',
        'Car Stereo',
        'Car Speaker',
        'Car Amplifier',
        'GPS Tracker',
        'Car GPS Navigator',
        'Tyre Inflator',
        'Car Vacuum',
        'Brake Pad',
        'Air Filter',
        'Spark Plug',
        'Wiper Blade',
        'Car Tyre',
        'Alloy Wheel',
        // Industrial / power tools
        'Electric Drill',
        'Angle Grinder',
        'Circular Saw',
        'Impact Driver',
        'Arc Welder',
        'Air Compressor',
        'Industrial Generator',
        'Hydraulic Jack',
        'Concrete Mixer',
    ];

    public static function supportsProductCondition(string $productTypeName): bool
    {
        return in_array($productTypeName, self::PRODUCT_CONDITION_ELIGIBLE_TYPE_NAMES, true);
    }

    /**
     * @return list<string> attribute slugs for the type, or empty for non-China / unmapped
     */
    public static function attributeSlugsFor(CatalogProductType $type): array
    {
        $type->loadMissing(['subcategory.department']);

        $category = $type->subcategory;
        if ($category === null || $category->origin !== CatalogOrigin::China) {
            return [];
        }

        $departmentSlug = $category->department?->slug;
        if ($departmentSlug === null) {
            return [];
        }

        $name = $type->name;

        return match ($departmentSlug) {
            'mens-fashion', 'womens-fashion' => self::fashionSlugs($name),
            'phones-tablets' => self::phonesSlugs($name),
            'computers-office' => self::computersSlugs($name),
            'consumer-electronics' => self::consumerElectronicsSlugs($name),
            'professional-audio' => self::professionalAudioSlugs($name),
            'home-appliances' => self::homeAppliancesSlugs($name),
            'home-furniture' => self::homeFurnitureSlugs($name),
            'beauty-personal-care' => self::beautySlugs($name),
            'health-medical' => self::healthSlugs($name),
            'jewelry-watches' => self::jewelrySlugs($name),
            'sports-outdoors' => self::sportsSlugs($name),
            'automotive' => self::automotiveSlugs($name),
            'industrial-tools' => self::industrialSlugs($name),
            'toys-kids' => self::toysSlugs($name),
            'pet-supplies' => self::petSlugs($name),
            'groceries' => self::groceriesSlugs($name),
            default => ['brand'],
        };
    }

    public static function isVariantCapable(CatalogProductType $type): bool
    {
        $slugs = self::attributeSlugsFor($type);

        return count(array_intersect($slugs, self::VARIANT_ATTRIBUTE_SLUGS)) > 0;
    }

    public static function isSimpleFriendly(CatalogProductType $type): bool
    {
        return ! self::isVariantCapable($type);
    }

    /**
     * @return list<string>
     */
    private static function fashionSlugs(string $name): array
    {
        $base = ['brand', 'color', 'size', 'material', 'gender', 'fabric', 'style'];

        if (in_array($name, ['Sneakers', 'Formal Shoes', 'Boots', 'Sandals', 'Slippers', 'High Heels', 'Flats', "Women's Boots"], true)) {
            return ['brand', 'color', 'shoe-size', 'material', 'gender', 'style'];
        }

        return $base;
    }

    /**
     * @return list<string>
     */
    private static function phonesSlugs(string $name): array
    {
        if (in_array($name, [
            'Android Smartphone', 'iPhone', 'Foldable Phone', 'Gaming Phone', 'Feature Phone',
            'Android Tablet', 'iPad',
        ], true)) {
            return ['brand', 'color', 'ram', 'storage', 'screen-size', 'battery-capacity', 'camera'];
        }

        // Accessories / cables — specs + color, no apparel size, no phone RAM matrix
        return ['brand', 'color', 'build-material', 'model'];
    }

    /**
     * @return list<string>
     */
    private static function computersSlugs(string $name): array
    {
        if (str_contains($name, 'Laptop') || str_contains($name, 'Desktop') || str_contains($name, 'PC') || str_contains($name, 'Ultrabook')) {
            return ['brand', 'color', 'model', 'ram', 'storage', 'screen-size'];
        }

        if (
            str_contains($name, 'UPS')
            || str_contains($name, 'Power Supply')
            || str_contains($name, 'Router Backup')
            || in_array($name, ['Router', 'Network Switch', 'Access Point'], true)
        ) {
            return ['brand', 'color', 'model', 'voltage', 'power'];
        }

        return ['brand', 'color', 'model', 'build-material'];
    }

    /**
     * @return list<string>
     */
    private static function consumerElectronicsSlugs(string $name): array
    {
        if (in_array($name, ['LED TV', 'OLED TV', 'Smart TV'], true)) {
            return ['brand', 'color', 'model', 'screen-size', 'voltage'];
        }

        if (in_array($name, ['Smart Watch'], true) || str_contains($name, 'Camera') || str_contains($name, 'Console')) {
            return ['brand', 'color', 'model', 'storage'];
        }

        return ['brand', 'color', 'model', 'build-material'];
    }

    /**
     * @return list<string>
     */
    private static function professionalAudioSlugs(string $name): array
    {
        $paCore = [
            'Portable PA System', 'Complete Sound System', 'Active PA Speaker', 'Passive PA Speaker',
            'Line Array System', 'Analog Mixer', 'Digital Mixer', 'DJ Mixer', 'Power Amplifier',
            'Integrated Amplifier', 'Passive Speaker', 'Studio Monitor', 'Ceiling Speaker',
            'Studio Monitor Speaker', 'DJ Controller', 'DJ Turntable',
        ];

        if (in_array($name, $paCore, true)) {
            return ['brand', 'power-output', 'speaker-size', 'channels', 'bluetooth', 'wireless', 'frequency-response', 'model'];
        }

        if (in_array($name, [
            'XLR Cable', 'Speakon Cable', 'TRS Cable', 'XLR Connector',
        ], true)) {
            return ['brand', 'connector-type', 'cable-length', 'color', 'compatibility'];
        }

        if (in_array($name, [
            'USB Audio Interface', 'Thunderbolt Audio Interface', 'Audio Interface',
        ], true)) {
            return ['brand', 'interface-type', 'channels', 'compatibility', 'model'];
        }

        if (in_array($name, ['Studio Headphone', 'DJ Headphone'], true)) {
            return ['brand', 'impedance', 'frequency-response', 'color', 'model'];
        }

        if (in_array($name, [
            'Wired Microphone', 'Wireless Microphone', 'Conference Microphone',
            'Microphone Stand', 'Microphone Shock Mount', 'Pop Filter',
            'Acoustic Foam Panel', 'Reflection Filter', 'DI Box',
        ], true)) {
            return ['brand', 'connector-type', 'impedance', 'frequency-response', 'compatibility', 'model'];
        }

        return ['brand', 'model', 'compatibility'];
    }

    /**
     * @return list<string>
     */
    private static function homeAppliancesSlugs(string $name): array
    {
        $specs = ['brand', 'model', 'voltage', 'power', 'capacity', 'energy-rating', 'build-material', 'dimensions'];

        $colorVariant = [
            'Refrigerator', 'Chest Freezer', 'Mini Fridge', 'Front Load Washing Machine',
            'Top Load Washing Machine', 'Clothes Dryer', 'Split Air Conditioner',
            'Portable Air Conditioner', 'Standing Fan', 'Ceiling Fan', 'Electric Kettle',
            'Temperature Control Kettle', 'Air Fryer', 'Air Fryer Oven', 'Countertop Microwave',
            'Grill Microwave', 'Drip Coffee Maker', 'Espresso Machine', 'Capsule Coffee Maker',
        ];

        if (in_array($name, ['Appliance Spare Part', 'Refrigerator Shelf', 'Washing Machine Hose'], true)) {
            return ['brand', 'model', 'build-material', 'compatibility'];
        }

        if (in_array($name, $colorVariant, true)) {
            return array_values(array_unique([...$specs, 'color']));
        }

        return $specs;
    }

    /**
     * @return list<string>
     */
    private static function homeFurnitureSlugs(string $name): array
    {
        $base = ['brand', 'build-material', 'color', 'dimensions', 'style'];

        if (in_array($name, ['Foam Mattress', 'Spring Mattress', 'Baby Mattress'], true)) {
            return ['brand', 'build-material', 'mattress-size', 'dimensions', 'color'];
        }

        if (in_array($name, ['Sofa', 'Armchair', 'Dining Chair', 'Office Chair', 'Bar Stool', 'Patio Chair', 'Outdoor Bench'], true)) {
            return [...$base, 'furniture-size', 'seating-capacity'];
        }

        if (in_array($name, ['Bed Frame', 'Wardrobe', 'Dining Table', 'Dining Set', 'Office Desk', 'Kids Bed', 'Kids Study Desk'], true)) {
            return [...$base, 'furniture-size'];
        }

        // Dressing Table must NOT receive apparel size — exact name path only
        return $base;
    }

    /**
     * @return list<string>
     */
    private static function beautySlugs(string $name): array
    {
        $makeup = ['Foundation', 'Lipstick', 'Eyeshadow Palette', 'Mascara', 'Concealer', 'Nail Polish'];
        $wigs = ['Lace Front Wig', 'Full Lace Wig', 'Hair Extension', 'Clip-In Extension'];
        $volume = [
            'Body Wash', 'Body Scrub', 'Shampoo', 'Hair Conditioner', 'Shampoo and Conditioner Set',
            'Hair Mask', 'Hair Oil', 'Hair Serum', 'Face Moisturizer', 'Night Cream',
            'Vitamin C Serum', 'Hyaluronic Acid Serum', 'Retinol Serum', 'Face Sunscreen',
            'Body Sunscreen', 'Micellar Water', 'Facial Cleanser', 'Mouthwash',
            'Eau de Parfum', 'Eau de Toilette', 'Body Mist', "Men's Face Wash", 'Aftershave',
            'Hand Cream',
        ];

        if (in_array($name, $wigs, true)) {
            return ['brand', 'color', 'wig-length', 'wig-texture', 'build-material'];
        }

        if (in_array($name, $makeup, true)) {
            return ['brand', 'shade', 'finish', 'volume', 'skin-type'];
        }

        if (in_array($name, ['Eau de Parfum', 'Eau de Toilette', 'Body Mist'], true)) {
            return ['brand', 'fragrance-family', 'volume'];
        }

        if (in_array($name, $volume, true)) {
            return ['brand', 'volume', 'skin-type', 'hair-type', 'fragrance-family'];
        }

        if (in_array($name, ['Hair Dryer', 'Hair Straightener', 'Electric Shaver', 'Epilator', 'Beard Trimmer', 'Facial Cleansing Brush'], true)) {
            return ['brand', 'model', 'color', 'voltage', 'power'];
        }

        return ['brand', 'build-material', 'color'];
    }

    /**
     * @return list<string>
     */
    private static function healthSlugs(string $name): array
    {
        // Spec-only simple supplies — no apparel size, no forced variant axes
        $simple = [
            'Syringe', 'Cotton Wool', 'Alcohol Swab', 'Medical Tape',
            'Adhesive Bandage', 'Elastic Bandage', 'Antiseptic Solution',
            'Dental Floss', 'Mouthwash', 'Contact Lens Solution', 'Eye Drops',
        ];

        $devices = [
            'Nebulizer', 'Hospital Bed', 'Medical Suction Machine',
            'Digital Thermometer', 'Blood Pressure Monitor', 'Pulse Oximeter', 'Blood Glucose Meter',
            'Wheelchair', 'Electric Toothbrush', 'Massage Gun', 'Heating Pad',
        ];

        $sized = [
            'Medical Gloves', 'Surgical Mask', 'Face Shield', 'Protective Goggles',
            'Knee Support', 'Wrist Support', 'Back Support Belt', 'Ankle Support',
            'Crutches', 'Walking Frame', 'Walking Stick', 'Reading Glasses',
        ];

        if (in_array($name, $simple, true)) {
            return ['brand', 'model', 'material-spec', 'pack-label'];
        }

        if (in_array($name, $devices, true)) {
            return ['brand', 'model', 'measurement-range', 'accuracy', 'capacity', 'build-material', 'color'];
        }

        if (in_array($name, $sized, true)) {
            return ['brand', 'medical-size', 'build-material', 'pack-quantity', 'color'];
        }

        return ['brand', 'model', 'build-material', 'pack-label'];
    }

    /**
     * @return list<string>
     */
    private static function jewelrySlugs(string $name): array
    {
        $rings = ['Gold Ring', 'Silver Ring', 'Fashion Ring', 'Engagement Ring', "Men's Ring"];
        $necklaces = [
            'Pendant Necklace', 'Chain Necklace', 'Choker Necklace', 'Fashion Necklace',
            "Men's Chain Necklace", "Women's Necklace Set",
        ];
        $watches = [
            "Men's Analog Watch", "Men's Digital Watch", "Men's Chronograph Watch",
            "Women's Analog Watch", "Women's Fashion Watch", 'Smart Watch', 'Fitness Smart Watch',
        ];

        if (in_array($name, $rings, true)) {
            return ['brand', 'metal', 'color', 'ring-size', 'stone-type'];
        }

        if (in_array($name, $necklaces, true)) {
            return ['brand', 'metal', 'color', 'chain-length', 'stone-type'];
        }

        if (in_array($name, $watches, true)) {
            return ['brand', 'color', 'watch-movement', 'dial-size', 'strap-material', 'strap-color'];
        }

        if (in_array($name, ['Watch Strap'], true)) {
            return ['brand', 'strap-material', 'strap-color', 'color'];
        }

        if (in_array($name, ['Jewelry Box', 'Jewelry Organizer', 'Jewelry Display Stand', 'Watch Box'], true)) {
            return ['brand', 'build-material', 'color', 'dimensions'];
        }

        return ['brand', 'metal', 'color', 'stone-type'];
    }

    /**
     * @return list<string>
     */
    private static function sportsSlugs(string $name): array
    {
        $sportswear = ['Sports T-Shirt', 'Tracksuit', 'Sports Shorts'];
        $footwear = ['Hiking Boots', 'Running Shoes', 'Training Shoes', 'Football Boots'];

        if (in_array($name, $sportswear, true)) {
            return ['brand', 'color', 'size', 'material', 'gender'];
        }

        if (in_array($name, $footwear, true)) {
            return ['brand', 'color', 'shoe-size', 'build-material'];
        }

        if (in_array($name, ['Camping Tent', 'Sleeping Bag', 'Outdoor Backpack', 'Gym Bag', 'Water Bottle'], true)) {
            return ['brand', 'color', 'capacity', 'dimensions', 'weight', 'build-material'];
        }

        if (in_array($name, ['Dumbbell Set', 'Treadmill', 'Exercise Bike', 'Weight Bench'], true)) {
            return ['brand', 'model', 'weight', 'dimensions', 'capacity', 'color'];
        }

        return ['brand', 'build-material', 'color', 'dimensions', 'weight'];
    }

    /**
     * @return list<string>
     */
    private static function automotiveSlugs(string $name): array
    {
        // Phone Holder must use automotive fitment — never phone RAM/storage
        $base = ['brand', 'model', 'vehicle-make', 'vehicle-model', 'vehicle-year-range', 'fitment', 'build-material'];

        $electrical = [
            'Jump Starter', 'Tyre Inflator', 'LED Headlight Bulb', 'Fog Light', 'Interior LED Light',
            'Car Stereo', 'Car Speaker', 'Car Amplifier', 'Dash Camera', 'Dual Channel Dash Camera',
            'GPS Tracker', 'Car GPS Navigator', 'Car Charger', 'USB Car Charger', 'Car Vacuum', 'OBD Scanner',
        ];

        $colorful = [
            'Car Cover', 'Seat Cover', 'Steering Wheel Cover', 'Car Floor Mat', 'Phone Holder',
            'Seat Organizer', 'Motorcycle Cover', 'Motorcycle Gloves', 'Motorcycle Helmet',
        ];

        if (in_array($name, $electrical, true)) {
            return array_values(array_unique([...$base, 'voltage', 'power', 'dimensions', 'color']));
        }

        if (in_array($name, $colorful, true)) {
            return array_values(array_unique([...$base, 'color', 'dimensions']));
        }

        if (in_array($name, ['Car Shampoo', 'Dashboard Cleaner', 'Tire Shine', 'Car Wax', 'Polishing Compound', 'Coolant', 'Engine Oil Additive'], true)) {
            return ['brand', 'volume', 'pack-quantity', 'compatibility'];
        }

        return array_values(array_unique([...$base, 'dimensions', 'color']));
    }

    /**
     * @return list<string>
     */
    private static function industrialSlugs(string $name): array
    {
        $powerTools = ['Electric Drill', 'Angle Grinder', 'Circular Saw', 'Impact Driver', 'Arc Welder', 'Air Compressor', 'Industrial Generator', 'Hydraulic Jack', 'Concrete Mixer'];
        $handTools = ['Screwdriver Set', 'Adjustable Wrench', 'Hammer', 'Pliers Set', 'Tape Measure', 'Spirit Level', 'Trowel Set', 'Bench Vise'];

        if (in_array($name, $powerTools, true)) {
            return ['brand', 'model', 'voltage', 'power', 'capacity', 'speed-rpm', 'build-material', 'dimensions', 'color'];
        }

        if (in_array($name, $handTools, true)) {
            return ['brand', 'tool-size', 'build-material', 'dimensions'];
        }

        if (in_array($name, ['Digital Caliper', 'Laser Distance Meter', 'Electrical Multimeter'], true)) {
            return ['brand', 'model', 'measurement-range', 'accuracy', 'build-material'];
        }

        if (in_array($name, ['Extension Cord', 'Circuit Breaker', 'Cable Gland'], true)) {
            return ['brand', 'voltage', 'capacity', 'build-material', 'dimensions'];
        }

        // Hinge/Padlock/etc. — specs only, no apparel size / no forced variants
        if (in_array($name, ['Hinge', 'Padlock', 'Screw Assortment', 'Bolt and Nut Set', 'Wheelbarrow', 'Welding Electrode', 'Welding Helmet', 'Safety Helmet', 'Safety Gloves', 'Safety Goggles', 'High Visibility Vest', 'Work Bench', 'Tool Cabinet'], true)) {
            return ['brand', 'model', 'build-material', 'dimensions', 'material-spec'];
        }

        return ['brand', 'build-material', 'dimensions', 'model', 'material-spec'];
    }

    /**
     * @return list<string>
     */
    private static function toysSlugs(string $name): array
    {
        $clothing = ['Kids Cap', 'Kids Socks Pack', 'Kids Hair Accessory'];
        $furniture = ['Kids Study Desk', 'Kids Chair', 'Kids Bed', 'High Chair', 'Baby Crib', 'Baby Mattress'];

        if (in_array($name, $clothing, true)) {
            return ['brand', 'color', 'kids-size', 'material'];
        }

        if (in_array($name, $furniture, true)) {
            return ['brand', 'color', 'kids-size', 'build-material', 'dimensions'];
        }

        if (in_array($name, ['Baby Diaper', 'Baby Wipes'], true)) {
            return ['brand', 'pack-quantity', 'kids-size'];
        }

        return ['brand', 'color', 'recommended-age', 'build-material', 'dimensions', 'battery-requirement'];
    }

    /**
     * @return list<string>
     */
    private static function petSlugs(string $name): array
    {
        if (in_array($name, ['Dry Dog Food', 'Dry Cat Food', 'Pet Treats'], true)) {
            return ['brand', 'pet-type', 'flavor', 'net-weight', 'pack-quantity'];
        }

        if (in_array($name, ['Dog Collar', 'Dog Leash', 'Dog Kennel', 'Dog Bed', 'Dog Jacket', 'Dog Chew Toy', 'Cat Litter Box', 'Cat Scratching Post', 'Cat Carrier', 'Cat Bed', 'Cat Wand Toy', 'Pet Sofa', 'Pet Costume'], true)) {
            return ['brand', 'pet-type', 'breed-size', 'pet-size', 'color', 'build-material'];
        }

        return ['brand', 'pet-type', 'build-material', 'color', 'pack-quantity'];
    }

    /**
     * @return list<string>
     */
    private static function groceriesSlugs(string $name): array
    {
        // Never Color or apparel Size
        if (in_array($name, ['Food Storage Container', 'Zip Lock Bag', 'Cling Film'], true)) {
            return ['brand', 'pack-label', 'build-material', 'dimensions'];
        }

        $variantFriendly = [
            'Potato Chips', 'Biscuits', 'Nuts Mix', 'Soft Drink', 'Fruit Juice',
            'Black Tea', 'Green Tea', 'Instant Coffee', 'Ground Coffee',
            'Tomato Sauce', 'Soy Sauce', 'Chili Sauce', 'Mayonnaise',
            'Chocolate Bar', 'Hard Candy', 'Gummy Candy', 'Peanut Butter',
            'Instant Noodles', 'Breakfast Cereal',
        ];

        if (in_array($name, $variantFriendly, true)) {
            return ['brand', 'net-weight', 'pack-quantity', 'flavor', 'ingredients', 'country-of-origin', 'dietary-type'];
        }

        // Staples — specification only
        return ['brand', 'pack-label', 'ingredients', 'country-of-origin', 'dietary-type-label'];
    }
}
