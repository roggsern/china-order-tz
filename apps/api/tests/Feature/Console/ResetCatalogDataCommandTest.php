<?php

namespace Tests\Feature\Console;

use App\Models\Admin;
use App\Models\Brand;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductType;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use Database\Seeders\ProductTypeSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResetCatalogDataCommandTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        Artisan::call('list', ['--raw' => true]);

        $this->assertStringContainsString('app:reset-catalog-data', Artisan::output());
    }

    public function test_command_deletes_product_data_and_preserves_catalog_structure_and_users(): void
    {
        $this->seed(ProductTypeSeeder::class);

        $user = User::factory()->create();
        Admin::factory()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $store = Store::query()->create([
            'code' => 'QASTORE',
            'name' => 'QA Store',
            'slug' => 'qa-store',
            'is_active' => true,
        ]);
        $attribute = CatalogAttribute::factory()->create();
        CatalogAttributeOption::factory()->create(['catalog_attribute_id' => $attribute->id]);
        $productTypeCount = ProductType::query()->count();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'store_id' => $store->id,
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        ProductMedia::factory()->create(['product_id' => $product->id]);
        VariantPrice::factory()->create(['product_variant_id' => $variant->id]);
        Inventory::factory()->forVariant($variant)->create(['product_id' => $product->id]);
        VariantInventory::factory()->create(['product_variant_id' => $variant->id]);
        Review::factory()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
        ]);
        Notification::factory()->create(['user_id' => $user->id]);

        $this->artisan('app:reset-catalog-data --force')
            ->assertSuccessful()
            ->expectsOutputToContain('Deleted:')
            ->expectsOutputToContain('Products: 1')
            ->expectsOutputToContain('Variants: 1')
            ->expectsOutputToContain('Preserved:')
            ->expectsOutputToContain('Catalog reset completed.');

        $this->assertSame(0, DB::table('products')->count());
        $this->assertSame(0, DB::table('product_variants')->count());
        $this->assertSame(0, DB::table('product_media')->count());
        $this->assertSame(0, DB::table('variant_prices')->count());
        $this->assertSame(0, DB::table('inventory')->count());
        $this->assertSame(0, DB::table('variant_inventories')->count());
        $this->assertSame(0, DB::table('reviews')->count());
        $this->assertSame(0, DB::table('notifications')->count());

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Admin::query()->count());
        $this->assertSame(1, Category::query()->count());
        $this->assertSame(1, Brand::query()->count());
        $this->assertSame(1, Store::query()->count());
        $this->assertSame(1, CatalogAttribute::query()->count());
        $this->assertSame(1, CatalogAttributeOption::query()->count());
        $this->assertSame($productTypeCount, ProductType::query()->count());
    }

    public function test_command_requires_confirmation_without_force_option(): void
    {
        Product::factory()->create();

        $this->artisan('app:reset-catalog-data')
            ->expectsConfirmation('Delete all product catalog data?', 'no')
            ->assertSuccessful()
            ->expectsOutputToContain('Cancelled.');

        $this->assertSame(1, DB::table('products')->count());
    }
}
