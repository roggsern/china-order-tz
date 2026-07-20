<?php

namespace Database\Seeders;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\CatalogProductType;
use App\Models\Product;
use App\Services\ProductConfiguration\ResolveTypeFromCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds Product Core records linked to brands + catalog product types.
 * Does not seed images, inventory, pricing modules, or variants.
 */
class ProductCoreSeeder extends Seeder
{
    /**
     * @return list<array{
     *     name: string,
     *     brand: string,
     *     type_matchers: list<string>,
     *     status?: string,
     *     featured?: bool,
     *     short?: string
     * }>
     */
    public static function definitions(): array
    {
        return [
            // Phones
            ['name' => 'Samsung Galaxy S25', 'brand' => 'Samsung', 'type_matchers' => ['Android Smartphone', 'Smartphone'], 'featured' => true, 'short' => 'Flagship Android smartphone'],
            ['name' => 'iPhone 17', 'brand' => 'Apple', 'type_matchers' => ['iPhone', 'Android Smartphone', 'Smartphone'], 'featured' => true, 'short' => 'Latest Apple flagship'],
            ['name' => 'Tecno Camon', 'brand' => 'Tecno', 'type_matchers' => ['Android Smartphone', 'Smartphone'], 'short' => 'Camera-focused smartphone'],
            ['name' => 'Xiaomi Redmi Note', 'brand' => 'Xiaomi', 'type_matchers' => ['Android Smartphone', 'Smartphone']],
            ['name' => 'Google Pixel', 'brand' => 'Google', 'type_matchers' => ['Android Smartphone', 'Smartphone'], 'featured' => true],
            ['name' => 'Huawei Nova', 'brand' => 'Huawei', 'type_matchers' => ['Android Smartphone', 'Smartphone']],
            ['name' => 'OnePlus Nord', 'brand' => 'OnePlus', 'type_matchers' => ['Android Smartphone', 'Smartphone']],
            ['name' => 'Honor Magic', 'brand' => 'Honor', 'type_matchers' => ['Android Smartphone', 'Smartphone']],
            ['name' => 'Infinix Hot', 'brand' => 'Infinix', 'type_matchers' => ['Android Smartphone', 'Smartphone']],
            ['name' => 'Samsung Galaxy A Series', 'brand' => 'Samsung', 'type_matchers' => ['Android Smartphone', 'Smartphone']],

            // Professional Audio
            ['name' => 'JBL EON', 'brand' => 'JBL', 'type_matchers' => ['Active PA Speaker', 'Portable PA System', 'PA Speaker'], 'featured' => true, 'short' => 'Portable powered PA speaker'],
            ['name' => 'Yamaha MG Mixer', 'brand' => 'Yamaha', 'type_matchers' => ['Analog Mixer', 'Digital Mixer', 'Mixer'], 'featured' => true, 'short' => 'Compact analog mixing console'],
            ['name' => 'RCF Speaker', 'brand' => 'RCF', 'type_matchers' => ['Active PA Speaker', 'Passive PA Speaker', 'PA Speaker'], 'short' => 'Professional PA loudspeaker'],
            ['name' => 'BOSE S1 Pro', 'brand' => 'BOSE', 'type_matchers' => ['Portable PA System', 'Active PA Speaker']],
            ['name' => 'Behringer Xenyx Mixer', 'brand' => 'Behringer', 'type_matchers' => ['Analog Mixer', 'Mixer']],
            ['name' => 'Mackie Thump', 'brand' => 'Mackie', 'type_matchers' => ['Active PA Speaker', 'PA Speaker']],
            ['name' => 'QSC K Series', 'brand' => 'QSC', 'type_matchers' => ['Active PA Speaker', 'PA Speaker'], 'featured' => true],
            ['name' => 'JBL Complete Sound System', 'brand' => 'JBL', 'type_matchers' => ['Complete Sound System', 'Portable PA System']],
            ['name' => 'Yamaha Wireless Microphone', 'brand' => 'Yamaha', 'type_matchers' => ['Wireless Microphone', 'Microphone']],
            ['name' => 'RCF Line Array', 'brand' => 'RCF', 'type_matchers' => ['Line Array System', 'Complete Sound System']],

            // Fashion
            ['name' => 'Nike Air Max', 'brand' => 'Nike', 'type_matchers' => ['Sneakers'], 'featured' => true, 'short' => 'Iconic lifestyle sneakers'],
            ['name' => "Levi's Jeans", 'brand' => "Levi's", 'type_matchers' => ['Casual Shirt', 'Denim Shirt', 'Formal Shirt', 'Round Neck T-Shirt'], 'featured' => true, 'short' => 'Classic denim jeans'],
            ['name' => 'Zara Dress', 'brand' => 'Zara', 'type_matchers' => ['Midi Dress', 'Maxi Dress', 'Mini Dress', 'Dress'], 'short' => 'Contemporary fashion dress'],
            ['name' => 'Adidas Ultraboost', 'brand' => 'Adidas', 'type_matchers' => ['Sneakers'], 'featured' => true],
            ['name' => 'Puma RS-X', 'brand' => 'Puma', 'type_matchers' => ['Sneakers']],
            ['name' => 'H&M Casual Tee', 'brand' => 'H&M', 'type_matchers' => ['Round Neck T-Shirt', 'Oversized T-Shirt', 'T-Shirt']],
            ['name' => 'Gucci Loafer', 'brand' => 'Gucci', 'type_matchers' => ['Formal Shoes', 'Flats', 'Sneakers']],
            ['name' => 'Nike Dri-FIT Polo', 'brand' => 'Nike', 'type_matchers' => ['Polo Shirt', 'Round Neck T-Shirt']],
            ['name' => 'Adidas Track Pants', 'brand' => 'Adidas', 'type_matchers' => ['Casual Shirt', 'Round Neck T-Shirt', 'Polo Shirt']],
            ['name' => 'Zara Mini Dress', 'brand' => 'Zara', 'type_matchers' => ['Mini Dress', 'Party Dress']],
            ['name' => 'Levi\'s Denim Jacket', 'brand' => "Levi's", 'type_matchers' => ['Denim Shirt', 'Casual Shirt']],
            ['name' => 'Puma Hoodie', 'brand' => 'Puma', 'type_matchers' => ['Oversized T-Shirt', 'Long Sleeve T-Shirt', 'Round Neck T-Shirt']],
        ];
    }

    public function run(): void
    {
        $resolveType = app(ResolveTypeFromCategory::class);
        $brands = Brand::query()->get()->keyBy(fn (Brand $brand) => Str::lower($brand->name));
        $types = CatalogProductType::query()->with('subcategory')->get();

        if ($brands->isEmpty() || $types->isEmpty()) {
            $this->command?->warn('ProductCoreSeeder skipped: brands or catalog product types missing.');

            return;
        }

        foreach (self::definitions() as $index => $definition) {
            $brand = $brands->get(Str::lower($definition['brand']))
                ?? $brands->first(fn (Brand $item) => str_contains(Str::lower($item->name), Str::lower($definition['brand'])));

            $catalogType = $this->resolveCatalogType($types, $definition['type_matchers']);

            if ($brand === null || $catalogType === null || $catalogType->subcategory_id === null) {
                $this->command?->warn('Skipped product: '.$definition['name']);

                continue;
            }

            $slug = Str::slug($definition['name']);
            $status = ProductLifecycleStatus::tryFromMixed($definition['status'] ?? 'active')
                ?? ProductLifecycleStatus::Active;

            $category = $catalogType->subcategory;
            $configType = $category ? $resolveType->handle($category) : null;

            Product::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'brand_id' => $brand->id,
                    'catalog_product_type_id' => $catalogType->id,
                    'category_id' => $catalogType->subcategory_id,
                    'product_type_id' => $configType?->id,
                    'sku' => 'CORE-'.strtoupper(Str::slug($definition['name'], '')),
                    'short_description' => $definition['short'] ?? $definition['name'],
                    'description' => ($definition['short'] ?? $definition['name']).' — Product Core seed item for CHINA ORDER TZ.',
                    'price' => 0,
                    'lifecycle_status' => $status,
                    'visibility' => ProductVisibility::Public,
                    'is_active' => $status->syncIsActiveFlag(),
                    'is_featured' => (bool) ($definition['featured'] ?? false),
                    'sort_order' => $index + 1,
                    'is_demo' => true,
                ],
            );
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CatalogProductType>  $types
     * @param  list<string>  $matchers
     */
    private function resolveCatalogType($types, array $matchers): ?CatalogProductType
    {
        foreach ($matchers as $matcher) {
            $exact = $types->first(
                fn (CatalogProductType $type) => strcasecmp($type->name, $matcher) === 0,
            );

            if ($exact !== null) {
                return $exact;
            }
        }

        foreach ($matchers as $matcher) {
            $partial = $types->first(
                fn (CatalogProductType $type) => stripos($type->name, $matcher) !== false,
            );

            if ($partial !== null) {
                return $partial;
            }
        }

        return $types->first();
    }
}
