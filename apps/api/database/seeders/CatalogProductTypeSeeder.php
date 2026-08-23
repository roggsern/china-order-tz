<?php

namespace Database\Seeders;

use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Support\Catalog\MobileAccessoriesTaxonomy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds catalog taxonomy product types under subcategory/category parents.
 * Does not touch configuration-schema ProductType rows.
 */
class CatalogProductTypeSeeder extends Seeder
{
    /**
     * Departments whose catalog product types should be moved in place by name
     * when re-seeding (preserves IDs and avoids duplicate rows on slug changes).
     *
     * @var list<string>
     */
    private const NAME_BASED_UPSERT_DEPARTMENTS = [
        'automotive',
        'beauty-personal-care',
        'computers-office',
        'consumer-electronics',
        'groceries',
        'health-medical',
        'home-appliances',
        'home-furniture',
        'home-care',
        'industrial-tools',
        'jewelry-watches',
        'mens-fashion',
        'pet-supplies',
        'phones-tablets',
        'professional-audio',
        'sports-outdoors',
        'toys-kids',
        'womens-fashion',
    ];

    /**
     * @return array<string, array<string, list<string>>>
     */
    public static function definitions(): array
    {
        return [
            'mens-fashion' => [
                'T-Shirts' => [
                    'Round Neck T-Shirt',
                    'Oversized T-Shirt',
                    'Long Sleeve T-Shirt',
                ],
                'Polo Shirts' => [
                    'Polo Shirt',
                ],
                'Shirts' => [
                    'Formal Shirt',
                    'Casual Shirt',
                    'Denim Shirt',
                ],
                'Hoodies' => [
                    'Hoodie',
                ],
                'Jackets' => [
                    'Jacket',
                ],
                'Jeans' => [
                    'Jeans',
                ],
                'Trousers' => [
                    'Trousers',
                ],
                'Shorts' => [
                    'Shorts',
                ],
                'Suits' => [
                    'Suit',
                ],
                'Sneakers' => [
                    'Sneakers',
                ],
                'Formal Shoes' => [
                    'Formal Shoes',
                ],
                'Boots' => [
                    'Boots',
                ],
                'Sandals' => [
                    'Sandals',
                ],
                'Slippers' => [
                    'Slippers',
                ],
                'Backpacks' => [
                    'Backpack',
                ],
                'Messenger Bags' => [
                    'Messenger Bag',
                ],
                'Duffel Bags' => [
                    'Duffel Bag',
                ],
                'Laptop Bags' => [
                    'Laptop Bag',
                ],
                'Travel Bags' => [
                    'Travel Bag',
                ],
                'Belts' => [
                    'Belt',
                ],
                'Wallets' => [
                    'Wallet',
                ],
                'Caps' => [
                    'Cap',
                ],
                'Sunglasses' => [
                    'Sunglasses',
                ],
                'Watches' => [
                    'Watch',
                ],
            ],
            'womens-fashion' => [
                'Maxi Dresses' => [
                    'Maxi Dress',
                ],
                'Midi Dresses' => [
                    'Midi Dress',
                ],
                'Mini Dresses' => [
                    'Mini Dress',
                ],
                'Evening Dresses' => [
                    'Evening Dress',
                ],
                'Party Dresses' => [
                    'Party Dress',
                ],
                'Office Dresses' => [
                    'Office Dress',
                ],
                'Blouses' => [
                    'Blouse',
                ],
                'T-Shirts' => [
                    "Women's T-Shirt",
                ],
                'Crop Tops' => [
                    'Crop Top',
                ],
                'Bodysuits' => [
                    'Bodysuit',
                ],
                'High Heels' => [
                    'High Heels',
                ],
                'Flats' => [
                    'Flats',
                ],
                'Sneakers' => [
                    'Sneakers',
                ],
                'Sandals' => [
                    'Sandals',
                ],
                'Boots' => [
                    "Women's Boots",
                ],
                'Pencil Skirts' => [
                    'Pencil Skirt',
                ],
                'Pleated Skirts' => [
                    'Pleated Skirt',
                ],
                'Maxi Skirts' => [
                    'Maxi Skirt',
                ],
                'Jeans' => [
                    'Jeans',
                ],
                'Leggings' => [
                    'Leggings',
                ],
                'Palazzo Pants' => [
                    'Palazzo Pants',
                ],
                'Tote Bags' => [
                    'Tote Bag',
                ],
                'Shoulder Bags' => [
                    'Shoulder Bag',
                ],
                'Crossbody Bags' => [
                    'Crossbody Bag',
                ],
                'Scarves' => [
                    'Scarf',
                ],
                "Women's Belts" => [
                    "Women's Belt",
                ],
                'Sunglasses' => [
                    'Sunglasses',
                ],
            ],
            'phones-tablets' => [
                'Android Phones' => [
                    'Android Smartphone',
                ],
                'iPhones' => [
                    'iPhone',
                ],
                'Foldable Phones' => [
                    'Foldable Phone',
                ],
                'Gaming Phones' => [
                    'Gaming Phone',
                ],
                'Phone Cases' => [
                    'Phone Case',
                ],
                'Chargers' => [
                    'Charger',
                    'Wireless Charger',
                ],
                'Power Banks' => [
                    'Power Bank',
                ],
                'Screen Protectors' => [
                    'Screen Protector',
                ],
                'Cables' => [
                    'USB-A Cable',
                    'USB-C Cable',
                    'Lightning Cable',
                    'Micro USB Cable',
                    'HDMI Cable',
                    'AUX Cable',
                    'Ethernet Cable',
                    'VGA Cable',
                    'DisplayPort Cable',
                ],
                'Feature Phones' => [
                    'Feature Phone',
                ],
                'Tablets' => [
                    'Android Tablet',
                    'iPad',
                ],
            ],
            'computers-office' => [
                'Laptops' => [
                    'Gaming Laptop',
                    'Business Laptop',
                    'Ultrabook',
                ],
                'Desktop Computers' => [
                    'Tower Desktop',
                    'All-in-One PC',
                    'Mini PC',
                ],
                'Monitors' => [
                    'Office Monitor',
                    'Gaming Monitor',
                    'Professional Monitor',
                ],
                'Printers' => [
                    'Inkjet Printer',
                    'Laser Printer',
                    'Thermal Printer',
                ],
                'Computer Accessories' => [
                    'Keyboard',
                    'Mouse',
                    'USB Hub',
                    'Docking Station',
                    'Webcam',
                    'Laptop Stand',
                    'Cooling Pad',
                ],
                'UPS & Backup Power' => [
                    'AC UPS',
                    'Desktop UPS',
                    'Network Equipment UPS',
                ],
                'DC UPS / Router Backup' => [
                    'DC UPS',
                    'Mini DC UPS',
                    'Router Backup Power Supply',
                ],
                'Routers & Networking' => [
                    'Router',
                    'Network Switch',
                    'Access Point',
                ],
                'Power Supplies' => [
                    'Computer Power Supply',
                    'Network Power Supply',
                ],
            ],
            'consumer-electronics' => [
                'TVs' => [
                    'LED TV',
                    'OLED TV',
                    'Smart TV',
                ],
                'Audio' => [
                    'Bluetooth Speaker',
                    'Soundbar',
                    'Wireless Headphones',
                ],
                'Cameras' => [
                    'DSLR Camera',
                    'Mirrorless Camera',
                    'Action Camera',
                ],
                'Smart Devices' => [
                    'Smart Watch',
                    'Smart Speaker',
                    'Smart Home Hub',
                ],
                'Gaming' => [
                    'Gaming Console',
                    'Gaming Controller',
                    'Gaming Headset',
                ],
            ],
            'professional-audio' => [
                'Portable PA Systems' => [
                    'Portable PA System',
                ],
                'Complete Sound Systems' => [
                    'Complete Sound System',
                ],
                'Active Speakers' => [
                    'Active PA Speaker',
                ],
                'Passive Speakers' => [
                    'Passive PA Speaker',
                ],
                'Line Array Systems' => [
                    'Line Array System',
                ],
                'Analog Mixers' => [
                    'Analog Mixer',
                ],
                'Digital Mixers' => [
                    'Digital Mixer',
                ],
                'DJ Mixers' => [
                    'DJ Mixer',
                ],
                'Power Amplifiers' => [
                    'Power Amplifier',
                ],
                'Integrated Amplifiers' => [
                    'Integrated Amplifier',
                ],
                'Wired Microphones' => [
                    'Wired Microphone',
                ],
                'Wireless Microphones' => [
                    'Wireless Microphone',
                ],
                'Conference Microphones' => [
                    'Conference Microphone',
                ],
                'Speakers' => [
                    'Passive Speaker',
                    'Studio Monitor',
                    'Ceiling Speaker',
                ],
                'Audio Accessories' => [
                    'XLR Cable',
                    'Microphone Stand',
                    'Audio Interface',
                    'DI Box',
                ],
                'Studio Equipment' => [
                    'Studio Monitor Speaker',
                    'Studio Headphone',
                    'Pop Filter',
                ],
                'DJ Equipment' => [
                    'DJ Controller',
                    'DJ Turntable',
                    'DJ Headphone',
                ],
                'Audio Interfaces' => [
                    'USB Audio Interface',
                    'Thunderbolt Audio Interface',
                ],
                'Recording Accessories' => [
                    'Microphone Shock Mount',
                    'Acoustic Foam Panel',
                    'Reflection Filter',
                ],
                'Cables & Connectors' => [
                    'Speakon Cable',
                    'TRS Cable',
                    'XLR Connector',
                ],
            ],
            'home-appliances' => [
                'Refrigerators & Freezers' => [
                    'Refrigerator',
                    'Chest Freezer',
                    'Mini Fridge',
                ],
                'Washing Machines & Dryers' => [
                    'Front Load Washing Machine',
                    'Top Load Washing Machine',
                    'Clothes Dryer',
                ],
                'Air Conditioners & Fans' => [
                    'Split Air Conditioner',
                    'Portable Air Conditioner',
                    'Standing Fan',
                    'Ceiling Fan',
                ],
                'Water Heaters & Dispensers' => [
                    'Electric Water Heater',
                    'Instant Water Heater',
                    'Water Dispenser',
                ],
                'Vacuum Cleaners' => [
                    'Canister Vacuum Cleaner',
                    'Robot Vacuum Cleaner',
                    'Handheld Vacuum Cleaner',
                ],
                'Irons & Garment Care' => [
                    'Steam Iron',
                    'Garment Steamer',
                ],
                'Home Appliance Parts' => [
                    'Appliance Spare Part',
                    'Refrigerator Shelf',
                    'Washing Machine Hose',
                ],
                'Cookers & Ovens' => [
                    'Electric Cooker',
                    'Gas Cooker',
                    'Microwave Oven',
                    'Electric Oven',
                ],
                'Microwaves' => [
                    'Countertop Microwave',
                    'Grill Microwave',
                ],
                'Electric Stoves' => [
                    'Electric Hot Plate',
                    'Induction Cooker',
                ],
                'Air Fryers' => [
                    'Air Fryer',
                    'Air Fryer Oven',
                ],
                'Blenders' => [
                    'Countertop Blender',
                    'Personal Blender',
                ],
                'Mixers' => [
                    'Stand Mixer',
                    'Hand Mixer',
                ],
                'Juicers' => [
                    'Centrifugal Juicer',
                    'Slow Juicer',
                ],
                'Food Processors' => [
                    'Food Processor',
                    'Chopper',
                ],
                'Electric Kettles' => [
                    'Electric Kettle',
                    'Temperature Control Kettle',
                ],
                'Coffee Makers' => [
                    'Drip Coffee Maker',
                    'Espresso Machine',
                    'Capsule Coffee Maker',
                ],
            ],
            'home-furniture' => [
                'Living Room Furniture' => [
                    'Sofa',
                    'Coffee Table',
                    'TV Stand',
                    'Armchair',
                ],
                'Bedroom Furniture' => [
                    'Bed Frame',
                    'Wardrobe',
                    'Nightstand',
                    'Dressing Table',
                ],
                'Dining Room Furniture' => [
                    'Dining Table',
                    'Dining Chair',
                    'Dining Set',
                ],
                'Office Furniture' => [
                    'Office Desk',
                    'Office Chair',
                    'Filing Cabinet',
                ],
                'Kitchen Furniture' => [
                    'Kitchen Cabinet',
                    'Kitchen Island',
                    'Bar Stool',
                ],
                'Mattresses & Bedding' => [
                    'Foam Mattress',
                    'Spring Mattress',
                    'Bedding Set',
                    'Pillow',
                ],
                'Home Decor' => [
                    'Wall Art',
                    'Decorative Vase',
                    'Curtain',
                    'Area Rug',
                ],
                'Lighting' => [
                    'Ceiling Light',
                    'Floor Lamp',
                    'Table Lamp',
                    'LED Strip Light',
                ],
                'Storage & Organization' => [
                    'Storage Shelf',
                    'Storage Box',
                    'Shoe Rack',
                ],
                'Bathroom Accessories' => [
                    'Bathroom Mirror',
                    'Toilet Shelf',
                    'Towel Rack',
                ],
                'Outdoor Furniture' => [
                    'Patio Chair',
                    'Garden Table',
                    'Outdoor Bench',
                ],
            ],
            'beauty-personal-care' => [
                'Wigs & Hair Extensions' => [
                    'Lace Front Wig',
                    'Full Lace Wig',
                    'Hair Extension',
                    'Clip-In Extension',
                ],
                'Makeup' => [
                    'Foundation',
                    'Lipstick',
                    'Eyeshadow Palette',
                    'Mascara',
                    'Concealer',
                ],
                'Fragrances' => [
                    'Eau de Parfum',
                    'Eau de Toilette',
                    'Body Mist',
                ],
                'Bath & Body' => [
                    'Body Wash',
                    'Body Scrub',
                    'Bath Bomb',
                    'Hand Cream',
                ],
                'Nail Care' => [
                    'Nail Polish',
                    'Nail Gel Kit',
                    'Nail Clipper Set',
                ],
                'Personal Care Appliances' => [
                    'Hair Dryer',
                    'Hair Straightener',
                    'Electric Shaver',
                    'Epilator',
                ],
                "Men's Grooming" => [
                    'Beard Trimmer',
                    'Men\'s Face Wash',
                    'Aftershave',
                ],
                'Shampoo & Conditioner' => [
                    'Shampoo',
                    'Hair Conditioner',
                    'Shampoo and Conditioner Set',
                ],
                'Hair Treatments' => [
                    'Hair Mask',
                    'Hair Oil',
                    'Hair Serum',
                ],
                'Hair Styling Products' => [
                    'Hair Gel',
                    'Hair Spray',
                    'Hair Wax',
                ],
                'Facial Cleansers' => [
                    'Facial Cleanser',
                    'Micellar Water',
                ],
                'Moisturizers' => [
                    'Face Moisturizer',
                    'Night Cream',
                ],
                'Serums' => [
                    'Vitamin C Serum',
                    'Hyaluronic Acid Serum',
                    'Retinol Serum',
                ],
                'Sunscreen' => [
                    'Face Sunscreen',
                    'Body Sunscreen',
                ],
                'Face Masks' => [
                    'Sheet Mask',
                    'Clay Mask',
                ],
                'Makeup Brushes' => [
                    'Makeup Brush Set',
                    'Foundation Brush',
                ],
                'Mirrors' => [
                    'Makeup Mirror',
                    'LED Makeup Mirror',
                ],
                'Facial Tools' => [
                    'Facial Roller',
                    'Facial Cleansing Brush',
                ],
            ],
            'health-medical' => [
                'Medical Equipment' => [
                    'Nebulizer',
                    'Hospital Bed',
                    'Medical Suction Machine',
                ],
                'Health Monitoring Devices' => [
                    'Digital Thermometer',
                    'Blood Pressure Monitor',
                    'Pulse Oximeter',
                    'Blood Glucose Meter',
                ],
                'Mobility Aids' => [
                    'Wheelchair',
                    'Crutches',
                    'Walking Frame',
                    'Walking Stick',
                ],
                'First Aid' => [
                    'First Aid Kit',
                    'Adhesive Bandage',
                    'Elastic Bandage',
                    'Antiseptic Solution',
                ],
                'Personal Protective Equipment' => [
                    'Medical Gloves',
                    'Face Shield',
                    'Surgical Mask',
                    'Protective Goggles',
                ],
                'Dental Care' => [
                    'Electric Toothbrush',
                    'Dental Floss',
                    'Mouthwash',
                ],
                'Vision Care' => [
                    'Contact Lens Solution',
                    'Reading Glasses',
                    'Eye Drops',
                ],
                'Rehabilitation & Support' => [
                    'Knee Support',
                    'Wrist Support',
                    'Back Support Belt',
                    'Ankle Support',
                ],
                'Home Health Care' => [
                    'Hot Water Bottle',
                    'Heating Pad',
                    'Massage Gun',
                ],
                'Medical Supplies' => [
                    'Syringe',
                    'Cotton Wool',
                    'Alcohol Swab',
                    'Medical Tape',
                ],
            ],
            'jewelry-watches' => [
                "Women's Jewelry" => [
                    'Women\'s Necklace Set',
                    'Women\'s Bracelet Set',
                    'Women\'s Earrings Set',
                ],
                "Men's Jewelry" => [
                    'Men\'s Chain Necklace',
                    'Men\'s Bracelet',
                    'Men\'s Ring',
                ],
                'Rings' => [
                    'Gold Ring',
                    'Silver Ring',
                    'Fashion Ring',
                    'Engagement Ring',
                ],
                'Necklaces' => [
                    'Pendant Necklace',
                    'Chain Necklace',
                    'Choker Necklace',
                ],
                'Earrings' => [
                    'Stud Earrings',
                    'Hoop Earrings',
                    'Drop Earrings',
                ],
                'Bracelets' => [
                    'Bangle Bracelet',
                    'Charm Bracelet',
                    'Cuff Bracelet',
                ],
                'Jewelry Sets' => [
                    'Necklace and Earrings Set',
                    'Bridal Jewelry Set',
                ],
                'Fashion Jewelry' => [
                    'Fashion Necklace',
                    'Fashion Earrings',
                    'Fashion Bracelet',
                    'Brooch',
                    'Anklet',
                ],
                'Jewelry Storage & Display' => [
                    'Jewelry Box',
                    'Jewelry Organizer',
                    'Jewelry Display Stand',
                ],
                "Men's Watches" => [
                    'Men\'s Analog Watch',
                    'Men\'s Digital Watch',
                    'Men\'s Chronograph Watch',
                ],
                "Women's Watches" => [
                    'Women\'s Analog Watch',
                    'Women\'s Fashion Watch',
                ],
                'Smart Watches' => [
                    'Smart Watch',
                    'Fitness Smart Watch',
                ],
                'Watch Accessories' => [
                    'Watch Strap',
                    'Watch Box',
                ],
            ],
            'sports-outdoors' => [
                'Outdoor Recreation' => [
                    'Camping Tent',
                    'Sleeping Bag',
                    'Outdoor Backpack',
                ],
                'Cycling' => [
                    'Bicycle',
                    'Bicycle Helmet',
                    'Bike Lock',
                    'Bike Pump',
                ],
                'Camping & Hiking' => [
                    'Hiking Boots',
                    'Camping Stove',
                    'Hiking Pole',
                    'Camping Lantern',
                ],
                'Water Sports' => [
                    'Swimming Goggles',
                    'Life Jacket',
                    'Inflatable Kayak',
                ],
                'Sportswear' => [
                    'Sports T-Shirt',
                    'Tracksuit',
                    'Sports Shorts',
                ],
                'Sports Shoes' => [
                    'Running Shoes',
                    'Training Shoes',
                    'Football Boots',
                ],
                'Sports Equipment' => [
                    'Dumbbell Set',
                    'Resistance Band',
                    'Skipping Rope',
                ],
                'Games & Leisure' => [
                    'Board Game',
                    'Playing Cards',
                    'Dart Board',
                ],
                'Gym Equipment' => [
                    'Treadmill',
                    'Exercise Bike',
                    'Weight Bench',
                ],
                'Yoga Equipment' => [
                    'Yoga Mat',
                    'Yoga Block',
                    'Yoga Strap',
                ],
                'Fitness Accessories' => [
                    'Gym Bag',
                    'Water Bottle',
                    'Fitness Tracker',
                ],
                'Football' => [
                    'Football',
                    'Football Goal',
                ],
                'Basketball' => [
                    'Basketball',
                    'Basketball Hoop',
                ],
                'Volleyball' => [
                    'Volleyball',
                    'Volleyball Net',
                ],
            ],
            'automotive' => [
                'Car Accessories' => [
                    'Car Cover',
                    'Seat Cover',
                    'Steering Wheel Cover',
                    'Car Vacuum',
                ],
                'Motorcycle Accessories' => [
                    'Motorcycle Helmet',
                    'Motorcycle Cover',
                    'Motorcycle Gloves',
                ],
                'Vehicle Parts' => [
                    'Brake Pad',
                    'Air Filter',
                    'Spark Plug',
                    'Wiper Blade',
                ],
                'Tools & Emergency Equipment' => [
                    'Jump Starter',
                    'Tyre Inflator',
                    'Car Jack',
                    'Emergency Road Kit',
                ],
                'Interior Accessories' => [
                    'Car Floor Mat',
                    'Phone Holder',
                    'Seat Organizer',
                ],
                'Exterior Accessories' => [
                    'Roof Rack',
                    'Car Spoiler',
                    'Mud Flap',
                ],
                'Lighting' => [
                    'LED Headlight Bulb',
                    'Fog Light',
                    'Interior LED Light',
                ],
                'Tires & Wheels' => [
                    'Car Tyre',
                    'Alloy Wheel',
                    'Tyre Sealant',
                ],
                'Car Audio' => [
                    'Car Stereo',
                    'Car Speaker',
                    'Car Amplifier',
                ],
                'Dash Cameras' => [
                    'Dash Camera',
                    'Dual Channel Dash Camera',
                ],
                'GPS & Tracking' => [
                    'GPS Tracker',
                    'Car GPS Navigator',
                ],
                'Car Chargers' => [
                    'Car Charger',
                    'USB Car Charger',
                ],
                'Cleaning Products' => [
                    'Car Shampoo',
                    'Dashboard Cleaner',
                    'Tire Shine',
                ],
                'Polishing & Detailing' => [
                    'Car Wax',
                    'Polishing Compound',
                    'Microfiber Cloth Set',
                ],
                'Repair & Maintenance' => [
                    'OBD Scanner',
                    'Engine Oil Additive',
                    'Coolant',
                ],
            ],
            'industrial-tools' => [
                'Power Tools' => [
                    'Electric Drill',
                    'Angle Grinder',
                    'Circular Saw',
                    'Impact Driver',
                ],
                'Hand Tools' => [
                    'Screwdriver Set',
                    'Adjustable Wrench',
                    'Hammer',
                    'Pliers Set',
                ],
                'Measuring Tools' => [
                    'Digital Caliper',
                    'Laser Distance Meter',
                    'Tape Measure',
                    'Spirit Level',
                ],
                'Welding Equipment' => [
                    'Arc Welder',
                    'Welding Helmet',
                    'Welding Electrode',
                ],
                'Safety Equipment' => [
                    'Safety Helmet',
                    'Safety Gloves',
                    'Safety Goggles',
                    'High Visibility Vest',
                ],
                'Electrical Equipment' => [
                    'Extension Cord',
                    'Circuit Breaker',
                    'Electrical Multimeter',
                    'Cable Gland',
                ],
                'Hardware' => [
                    'Screw Assortment',
                    'Bolt and Nut Set',
                    'Hinge',
                    'Padlock',
                ],
                'Workshop Equipment' => [
                    'Work Bench',
                    'Tool Cabinet',
                    'Bench Vise',
                ],
                'Construction Tools' => [
                    'Concrete Mixer',
                    'Wheelbarrow',
                    'Trowel Set',
                ],
                'Industrial Machinery' => [
                    'Air Compressor',
                    'Industrial Generator',
                    'Hydraulic Jack',
                ],
            ],
            'toys-kids' => [
                'Educational Toys' => [
                    'Building Blocks',
                    'Learning Tablet Toy',
                    'Alphabet Puzzle',
                ],
                'Dolls & Accessories' => [
                    'Fashion Doll',
                    'Doll House',
                    'Doll Accessories Set',
                ],
                'Remote Control Toys' => [
                    'Remote Control Car',
                    'Remote Control Drone',
                    'Remote Control Boat',
                ],
                'Outdoor Toys' => [
                    'Trampoline',
                    'Kids Scooter',
                    'Inflatable Pool',
                ],
                'Building Toys' => [
                    'Construction Brick Set',
                    'Magnetic Building Tiles',
                ],
                'Pretend Play' => [
                    'Play Kitchen Set',
                    'Doctor Play Set',
                    'Tool Play Set',
                ],
                "Kids' Furniture" => [
                    'Kids Study Desk',
                    'Kids Chair',
                    'Kids Bed',
                ],
                'School Supplies' => [
                    'School Backpack',
                    'Pencil Case',
                    'Notebook Set',
                ],
                "Kids' Clothing Accessories" => [
                    'Kids Cap',
                    'Kids Socks Pack',
                    'Kids Hair Accessory',
                ],
                'Baby Feeding' => [
                    'Baby Bottle',
                    'Baby Food Maker',
                    'High Chair',
                ],
                'Baby Care' => [
                    'Baby Diaper',
                    'Baby Wipes',
                    'Baby Monitor',
                ],
                'Strollers & Carriers' => [
                    'Baby Stroller',
                    'Baby Carrier',
                    'Car Seat',
                ],
                'Nursery Products' => [
                    'Baby Crib',
                    'Baby Mattress',
                    'Nursery Organizer',
                ],
            ],
            'pet-supplies' => [
                'Dog Supplies' => [
                    'Dog Collar',
                    'Dog Leash',
                    'Dog Kennel',
                ],
                'Cat Supplies' => [
                    'Cat Litter Box',
                    'Cat Scratching Post',
                    'Cat Carrier',
                ],
                'Pet Food' => [
                    'Dry Dog Food',
                    'Dry Cat Food',
                    'Pet Treats',
                ],
                'Pet Grooming' => [
                    'Pet Brush',
                    'Pet Shampoo',
                    'Nail Clipper for Pets',
                ],
                'Pet Beds & Furniture' => [
                    'Dog Bed',
                    'Cat Bed',
                    'Pet Sofa',
                ],
                'Pet Toys' => [
                    'Dog Chew Toy',
                    'Cat Wand Toy',
                    'Interactive Pet Toy',
                ],
                'Pet Clothing' => [
                    'Dog Jacket',
                    'Pet Costume',
                ],
                'Pet Health & Hygiene' => [
                    'Pet Vitamins',
                    'Flea Treatment',
                    'Pet Dental Care',
                ],
                'Aquatic Supplies' => [
                    'Aquarium Tank',
                    'Aquarium Filter',
                    'Aquarium Heater',
                ],
                'Bird Supplies' => [
                    'Bird Cage',
                    'Bird Feeder',
                    'Bird Perch',
                ],
            ],
            'home-care' => [
                'Pest Control' => [
                    'Insecticide Spray',
                ],
                'Cleaning & Hygiene' => [
                    'Cleaning Product',
                ],
                'Household Essentials' => [
                    'Household Essential',
                ],
                'Smart Home Care' => [
                    'Smart Home Device',
                ],
            ],
            'groceries' => [
                'Snacks' => [
                    'Potato Chips',
                    'Biscuits',
                    'Nuts Mix',
                ],
                'Beverages' => [
                    'Soft Drink',
                    'Fruit Juice',
                    'Bottled Water',
                ],
                'Tea & Coffee' => [
                    'Black Tea',
                    'Green Tea',
                    'Instant Coffee',
                    'Ground Coffee',
                ],
                'Breakfast Foods' => [
                    'Breakfast Cereal',
                    'Oats',
                    'Peanut Butter',
                ],
                'Baking Supplies' => [
                    'Baking Flour',
                    'Baking Powder',
                    'Cocoa Powder',
                ],
                'Cooking Ingredients' => [
                    'Cooking Oil',
                    'Rice',
                    'Pasta',
                    'Salt',
                ],
                'Sauces & Condiments' => [
                    'Tomato Sauce',
                    'Soy Sauce',
                    'Chili Sauce',
                    'Mayonnaise',
                ],
                'Canned & Packaged Foods' => [
                    'Canned Beans',
                    'Canned Tuna',
                    'Instant Noodles',
                ],
                'Candy & Chocolate' => [
                    'Chocolate Bar',
                    'Hard Candy',
                    'Gummy Candy',
                ],
                'Food Storage' => [
                    'Food Storage Container',
                    'Zip Lock Bag',
                    'Cling Film',
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $departmentSlug => $parents) {
            $department = Department::query()->where('slug', $departmentSlug)->first();

            if ($department === null) {
                continue;
            }

            foreach ($parents as $parentName => $typeNames) {
                $parent = $this->resolveParent($department->id, $parentName);

                if ($parent === null) {
                    continue;
                }

                foreach ($typeNames as $index => $name) {
                    if (
                        $departmentSlug === MobileAccessoriesTaxonomy::CONSUMER_ELECTRONICS_DEPARTMENT_SLUG
                        && MobileAccessoriesTaxonomy::isCompetingPowerBankTypeName($name)
                    ) {
                        continue;
                    }

                    $slug = $parent->slug.'-'.Str::slug($name);
                    $attributes = [
                        'subcategory_id' => $parent->id,
                        'name' => $name,
                        'image' => null,
                        'description' => null,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ];

                    if (in_array($departmentSlug, self::NAME_BASED_UPSERT_DEPARTMENTS, true)) {
                        $existing = CatalogProductType::query()
                            ->where('name', $name)
                            ->whereHas(
                                'subcategory',
                                fn ($query) => $query->where('department_id', $department->id),
                            )
                            ->first();

                        if ($existing !== null) {
                            $existing->update([
                                ...$attributes,
                                'slug' => $slug,
                            ]);

                            continue;
                        }
                    }

                    CatalogProductType::query()->updateOrCreate(
                        ['slug' => $slug],
                        $attributes,
                    );
                }
            }
        }
    }

    private function resolveParent(string $departmentId, string $parentName): ?Category
    {
        $subcategory = Category::query()
            ->where('department_id', $departmentId)
            ->where('name', $parentName)
            ->whereNotNull('parent_id')
            ->first();

        if ($subcategory !== null) {
            return $subcategory;
        }

        return Category::query()
            ->where('department_id', $departmentId)
            ->where('name', $parentName)
            ->whereNull('parent_id')
            ->first();
    }
}
