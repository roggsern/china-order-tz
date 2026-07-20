<?php

namespace Database\Seeders;

use App\Enums\ProductLifecycleStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Pricing\SyncConfigurationPriceTiers;
use Database\Support\DemoProductImageLibrary;
use Illuminate\Database\Seeder;

/**
 * Clean real-commerce fixtures for Sprint B manual / automated testing.
 *
 * - Test Customer (not demo graph)
 * - Simple Active product with wholesale tiers (Wireless Earbuds)
 * - Relies on Iphone16ProDemoSeeder for variable product (is_demo=false)
 */
class CommerceEngineTestSeeder extends Seeder
{
    public const TEST_CUSTOMER_EMAIL = 'test.customer@chinaordertz.com';

    public const SIMPLE_PRODUCT_SLUG = 'wireless-earbuds-pro';

    public function run(): void
    {
        $this->seedTestCustomer();
        $this->seedSimpleWholesaleProduct();
    }

    private function seedTestCustomer(): void
    {
        $customerRole = Role::query()->where('slug', 'customer')->first();
        if ($customerRole === null) {
            $this->command?->warn('CommerceEngineTestSeeder: customer role missing.');

            return;
        }

        // Plain password — User model casts `password` => `hashed` (bcrypt once).
        $user = User::query()->updateOrCreate(
            ['email' => self::TEST_CUSTOMER_EMAIL],
            [
                'name' => 'Test Customer',
                'phone' => '+255700100200',
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $user->roles()->syncWithoutDetaching([$customerRole->id]);
    }

    private function seedSimpleWholesaleProduct(): void
    {
        $productType = ProductType::query()->where('slug', 'simple-goods')->first()
            ?? ProductType::query()->where('has_configurations', false)->first();
        $category = Category::query()->where('slug', 'electronics-accessories')->first()
            ?? Category::query()->where('slug', 'accessories')->first()
            ?? Category::query()->whereNotNull('parent_id')->orderBy('sort_order')->first();
        $brand = Brand::query()->where('is_active', true)->first();
        $supplier = Supplier::query()->where('is_active', true)->first();

        if ($category === null) {
            $this->command?->warn('CommerceEngineTestSeeder: no category available for simple product.');

            return;
        }

        if ($productType !== null && $category->product_type_id === null) {
            $category->forceFill(['product_type_id' => $productType->id])->save();
        }

        $product = Product::query()->updateOrCreate(
            ['slug' => self::SIMPLE_PRODUCT_SLUG],
            [
                'category_id' => $category->id,
                'brand_id' => $brand?->id,
                'supplier_id' => $supplier?->id,
                'product_type_id' => $productType?->id ?? $category->product_type_id,
                'name' => 'Wireless Earbuds Pro',
                'sku' => 'EARBUDS-PRO-001',
                'short_description' => 'Simple product with wholesale quantity pricing for commerce engine tests.',
                'description' => "Retail and wholesale tiers on a non-variable product.\n\n"
                    ."Qty 1 — retail. Qty 5+ / 10+ unlock wholesale unit prices.",
                'price' => 450_000,
                'compare_at_price' => 520_000,
                'cost_price' => 280_000,
                'air_shipping_price' => 35_000,
                'sea_shipping_price' => 18_000,
                'weight' => 0.12,
                'is_active' => true,
                'is_featured' => true,
                'is_demo' => false,
                'lifecycle_status' => ProductLifecycleStatus::Active,
            ]
        );

        Inventory::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => 200,
                'reserved_quantity' => 0,
            ]
        );

        ProductImage::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'path' => DemoProductImageLibrary::publicPath('headphones.jpg'),
            ],
            [
                'alt_text' => 'Wireless Earbuds Pro',
                'is_primary' => true,
                'sort_order' => 0,
            ]
        );

        app(SyncConfigurationPriceTiers::class)->handle($product, null, [
            [
                'min_quantity' => 1,
                'tier_type' => 'fixed_unit',
                'unit_price' => 450_000,
            ],
            [
                'min_quantity' => 5,
                'tier_type' => 'fixed_unit',
                'unit_price' => 420_000,
            ],
            [
                'min_quantity' => 10,
                'tier_type' => 'fixed_unit',
                'unit_price' => 390_000,
            ],
        ]);
    }
}
