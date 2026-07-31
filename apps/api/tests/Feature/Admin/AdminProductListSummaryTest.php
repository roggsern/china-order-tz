<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductMediaType;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductListSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_product_listing_includes_summary_fields(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $store = Store::query()->create([
            'code' => 'DSM-01',
            'name' => 'Dar Central',
            'slug' => 'dar-central',
            'is_active' => true,
        ]);

        $product = Product::factory()->tzLocal()->create([
            'name' => 'List Summary Blouse',
            'slug' => 'list-summary-blouse',
            'price' => 20000,
            'store_id' => $store->id,
        ]);

        ProductMedia::query()->create([
            'product_id' => $product->id,
            'type' => ProductMediaType::Image,
            'url' => '/storage/products/blouse-primary.jpg',
            'alt_text' => 'Blouse primary',
            'title' => 'Primary',
            'is_primary' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $cheap = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Black / Small',
            'sku' => 'BLOUSE-BLK-S',
            'price' => null,
            'is_active' => true,
            'is_default' => true,
        ]);
        $expensive = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Blue / Medium',
            'sku' => 'BLOUSE-BLU-M',
            'price' => null,
            'is_active' => true,
            'is_default' => false,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $cheap->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 15000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $expensive->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 28000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        VariantInventory::query()->create([
            'product_variant_id' => $cheap->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 12,
            'reserved' => 2,
            'reorder_level' => 2,
            'safety_stock' => 0,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $expensive->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 0,
            'reserved' => 0,
            'reorder_level' => 2,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/admin/products?search=List+Summary+Blouse');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $product->id]);

        $row = collect($response->json('data'))->firstWhere('id', $product->id);
        $this->assertNotNull($row);

        $this->assertSame($store->id, $row['store']['id'] ?? null);
        $this->assertSame('Dar Central', $row['store']['name'] ?? null);
        $this->assertSame('TZ_LOCAL', $row['commerce_channel']['code'] ?? null);
        $this->assertSame(2, $row['variants_count']);
        $this->assertSame('15000.00', $row['price_range']['min'] ?? null);
        $this->assertSame('28000.00', $row['price_range']['max'] ?? null);
        $this->assertSame('TZS', $row['price_range']['currency'] ?? null);
        $this->assertSame('variant', $row['stock_summary']['path'] ?? null);
        $this->assertSame(10, $row['stock_summary']['total_available'] ?? null);
        $this->assertSame(1, $row['stock_summary']['variants_in_stock'] ?? null);
        $this->assertSame(1, $row['stock_summary']['variants_out_of_stock'] ?? null);
        $this->assertNotNull($row['image']['url'] ?? null);
        $this->assertSame('Blouse primary', $row['image']['alt_text'] ?? null);

        // Existing consumer fields remain present.
        $this->assertArrayHasKey('images', $row);
        $this->assertArrayHasKey('variants', $row);
        $this->assertArrayHasKey('price', $row);
        $this->assertArrayHasKey('store_id', $row);
    }

    public function test_product_listing_requires_catalog_view_permission(): void
    {
        Sanctum::actingAs(
            Admin::factory()->ordinary()->withPermissions([AdminPermissions::ORDERS_VIEW])->create(),
        );

        $this->getJson('/api/v1/admin/products')->assertForbidden();
    }

    public function test_simple_product_price_range_falls_back_to_product_price(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->fromChina()->create([
            'name' => 'Simple Summary Mug',
            'slug' => 'simple-summary-mug',
            'price' => 8500,
        ]);

        $row = collect(
            $this->getJson('/api/v1/admin/products?search=Simple+Summary+Mug')
                ->assertOk()
                ->json('data'),
        )->firstWhere('id', $product->id);

        $this->assertNotNull($row);
        $this->assertSame(0, $row['variants_count']);
        $this->assertSame('8500.00', $row['price_range']['min'] ?? null);
        $this->assertSame('8500.00', $row['price_range']['max'] ?? null);
        $this->assertSame('simple', $row['stock_summary']['path'] ?? null);
    }
}
