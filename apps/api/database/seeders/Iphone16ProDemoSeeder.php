<?php

namespace Database\Seeders;

use App\Enums\VariantPriceType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Models\ProductType;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\VariantPrice;
use App\Services\Inventory\CanonicalVariantInventoryInitializer;
use App\Services\ProductConfiguration\GenerateValidConfigurations;
use App\Services\ProductConfiguration\SyncProductConfigurations;
use Database\Support\DemoProductImageLibrary;
use Illuminate\Database\Seeder;

/**
 * Deterministic Product Configuration Engine demo: iPhone 16 Pro (Phones type).
 *
 * Uses existing GenerateValidConfigurations + SyncProductConfigurations —
 * no product-type-specific application logic.
 */
class Iphone16ProDemoSeeder extends Seeder
{
    public const SLUG = 'iphone-16-pro';

    public const SKU = 'IPH16PRO';

    /**
     * Base catalog price (TZS). Configuration rows override per storage tier.
     */
    private const BASE_PRICE = 3_499_000;

    /**
     * Per-storage unit price overrides (TZS).
     *
     * @var array<string, int>
     */
    private const STORAGE_PRICES = [
        '128gb' => 3_499_000,
        '256gb' => 3_899_000,
        '512gb' => 4_499_000,
        '1tb' => 5_299_000,
    ];

    /**
     * Stock per color+storage slug key (`{color}|{storage}`).
     * Zero stock demos out-of-stock picker disabling.
     *
     * @var array<string, int>
     */
    private const STOCK = [
        'silver|128gb' => 12,
        'silver|256gb' => 8,
        'silver|512gb' => 4,
        'silver|1tb' => 0, // OOS — storefront must disable
        'black|128gb' => 10,
        'black|256gb' => 6,
        'blue|256gb' => 5,
        'blue|512gb' => 3,
    ];

    public function run(): void
    {
        $productType = ProductType::query()->where('slug', 'phones')->first();
        // CatalogBible uses electronics-phones; keep legacy slug as fallback.
        $category = Category::query()
            ->whereIn('slug', ['electronics-phones', 'electronics-smartphones'])
            ->orderByRaw("CASE WHEN slug = 'electronics-phones' THEN 0 ELSE 1 END")
            ->first();
        $brand = Brand::query()->where('slug', 'apple')->first();
        $supplier = Supplier::query()->where('is_active', true)->first();

        if ($productType === null || $category === null || $brand === null) {
            $this->command?->warn('Iphone16ProDemoSeeder skipped: phones type, electronics-phones category, or Apple brand missing.');

            return;
        }

        $color = ProductAttribute::query()->where('slug', 'color')->firstOrFail();
        $storage = ProductAttribute::query()->where('slug', 'storage')->firstOrFail();

        $colorIds = $this->valueIds($color, ['silver', 'black', 'blue']);
        $storageIds = $this->valueIds($storage, ['128gb', '256gb', '512gb', '1tb']);

        if (count($colorIds) !== 3 || count($storageIds) !== 4) {
            $this->command?->warn('Iphone16ProDemoSeeder skipped: expected Color/Storage attribute values are missing.');

            return;
        }

        $product = Product::query()->updateOrCreate(
            ['slug' => self::SLUG],
            [
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'supplier_id' => $supplier?->id,
                'product_type_id' => $productType->id,
                'name' => 'iPhone 16 Pro',
                'sku' => self::SKU,
                'short_description' => 'Demo handset for the Product Configuration Engine — Color × Storage with live dependency rules and quotes.',
                'description' => "Product Configuration Engine demo product.\n\n"
                    ."Pick a Color, then Storage. Allowed Storage options follow attribute dependency rules "
                    ."(Silver: all sizes; Black: 128/256; Blue: 256/512). Prices come from the Live Quote API, "
                    .'including MOQ tiers on selected configurations. Out-of-stock options are disabled.',
                'price' => self::BASE_PRICE,
                'compare_at_price' => self::BASE_PRICE + 400_000,
                'cost_price' => 2_800_000,
                'air_shipping_price' => 85_000,
                'sea_shipping_price' => 45_000,
                'weight' => 0.199,
                'dimensions' => '15x7x1 cm',
                'is_active' => true,
                'is_featured' => true,
                'is_demo' => false,
                'lifecycle_status' => 'active',
                'meta_title' => 'iPhone 16 Pro — Configuration Demo',
                'meta_description' => 'Interactive Color and Storage configuration demo with live pricing.',
            ]
        );

        ProductImage::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'path' => DemoProductImageLibrary::publicPath('phone.jpg'),
            ],
            [
                'alt_text' => 'iPhone 16 Pro',
                'is_primary' => true,
                'sort_order' => 0,
            ]
        );

        $generator = app(GenerateValidConfigurations::class);
        $sync = app(SyncProductConfigurations::class);

        $combos = $generator->handle($productType, [
            $color->id => array_values($colorIds),
            $storage->id => array_values($storageIds),
        ]);

        $slugsById = ProductAttributeValue::query()
            ->whereIn('id', array_merge(array_values($colorIds), array_values($storageIds)))
            ->pluck('slug', 'id');

        $existingBySignature = $product->variants()
            ->with('attributeValues')
            ->get()
            ->keyBy(function ($variant) {
                return $variant->attributeValues
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->implode('|');
            });

        $rows = [];

        foreach ($combos as $combo) {
            $valueIds = $combo['attribute_value_ids'];
            $colorSlug = null;
            $storageSlug = null;

            foreach ($valueIds as $valueId) {
                $slug = $slugsById[$valueId] ?? null;
                if (in_array($slug, ['silver', 'black', 'blue'], true)) {
                    $colorSlug = $slug;
                }
                if (in_array($slug, ['128gb', '256gb', '512gb', '1tb'], true)) {
                    $storageSlug = $slug;
                }
            }

            if ($colorSlug === null || $storageSlug === null) {
                continue;
            }

            $stockKey = "{$colorSlug}|{$storageSlug}";
            $signature = collect($valueIds)->sort()->values()->implode('|');
            $existing = $existingBySignature->get($signature);

            $row = [
                'id' => $existing?->id,
                'attribute_value_ids' => $valueIds,
                'sku' => null, // Auto SKU from Product Type pattern
                'stock_quantity' => self::STOCK[$stockKey] ?? 0,
                'price' => self::STORAGE_PRICES[$storageSlug] ?? self::BASE_PRICE,
            ];

            // Wholesale demo on Black 256GB — quantity changes alter quote via price tiers.
            if ($stockKey === 'black|256gb') {
                $row['price_tiers'] = [
                    ['min_quantity' => 1, 'tier_type' => 'fixed_unit', 'unit_price' => 3_899_000],
                    ['min_quantity' => 3, 'tier_type' => 'fixed_unit', 'unit_price' => 3_749_000],
                ];
            }

            $rows[] = $row;
        }

        $sync->handle($product, $productType, $rows);

        // Iphone16ProDemoSeeder runs after VariantPrice/Inventory seeders — ensure cart engine rows exist.
        $this->ensureVariantCommerceRows($product->fresh(['variants']));

        $this->command?->info(sprintf(
            'iPhone 16 Pro demo ready: /products/%s (%d configurations)',
            self::SLUG,
            count($rows),
        ));
    }

    private function ensureVariantCommerceRows(Product $product): void
    {
        $initializer = app(CanonicalVariantInventoryInitializer::class);

        foreach ($product->variants as $variant) {
            $this->ensureRetailPrice($variant);
            // RC1-B1: establish MAIN from positive legacy stock (SyncProductConfigurations), never shadow at 0.
            $initializer->ensure($variant, [
                'warehouse_code' => 'MAIN',
                'requested_on_hand' => null,
                'reorder_level' => 2,
                'safety_stock' => 1,
                'is_active' => true,
                'reason' => 'Iphone16Pro demo seeder — opening MAIN from legacy stock',
            ]);
        }
    }

    private function ensureRetailPrice(ProductVariant $variant): void
    {
        $exists = VariantPrice::query()
            ->where('product_variant_id', $variant->id)
            ->where('price_type', VariantPriceType::Retail)
            ->where('currency', 'TZS')
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            return;
        }

        $amount = $variant->price ?? self::BASE_PRICE;

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => $amount,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<string>  $slugs
     * @return array<string, string> slug => id
     */
    private function valueIds(ProductAttribute $attribute, array $slugs): array
    {
        return ProductAttributeValue::query()
            ->where('product_attribute_id', $attribute->id)
            ->whereIn('slug', $slugs)
            ->get()
            ->mapWithKeys(fn (ProductAttributeValue $value) => [$value->slug => $value->id])
            ->all();
    }
}
