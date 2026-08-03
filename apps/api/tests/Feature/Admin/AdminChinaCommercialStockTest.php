<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductLifecycleStatus;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\ChinaCommercialStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Inventory\StockResolver;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminChinaCommercialStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(Admin::factory()->create());
    }

    public function test_admin_can_set_simple_china_commercial_stock(): void
    {
        $product = Product::factory()->fromChina()->create([
            'name' => 'China Charger',
            'slug' => 'china-charger',
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'price' => 15000,
        ]);

        $this->getJson('/api/v1/admin/products/'.$product->id.'/commercial-stock')
            ->assertOk()
            ->assertJsonPath('data.path', 'simple')
            ->assertJsonPath('data.simple.available_quantity', 0)
            ->assertJsonPath('data.variants', []);

        $this->patchJson('/api/v1/admin/products/'.$product->id.'/commercial-stock', [
            'available_quantity' => 12,
        ])
            ->assertOk()
            ->assertJsonPath('data.available_quantity', 12)
            ->assertJsonPath('data.reserved_quantity', 0);

        $this->assertDatabaseHas('china_commercial_stocks', [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'available_quantity' => 12,
        ]);

        $this->patchJson('/api/v1/admin/products/'.$product->id.'/commercial-stock', [
            'available_quantity' => 0,
        ])->assertOk()
            ->assertJsonPath('data.available_quantity', 0);
    }

    public function test_admin_can_set_variant_china_commercial_stock(): void
    {
        $product = Product::factory()->fromChina()->create([
            'name' => 'China Phone',
            'slug' => 'china-phone',
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'price' => 0,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Black 128GB',
            'sku' => 'CN-PHONE-BLK-128',
            'is_active' => true,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 1500000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/admin/products/'.$product->id.'/commercial-stock')
            ->assertOk()
            ->assertJsonPath('data.path', 'variant')
            ->assertJsonPath('data.simple', null)
            ->assertJsonPath('data.variants.0.variant_id', $variant->id)
            ->assertJsonPath('data.variants.0.available_quantity', 0);

        $this->patchJson('/api/v1/admin/variants/'.$variant->id.'/commercial-stock', [
            'available_quantity' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.available_quantity', 5)
            ->assertJsonPath('data.product_variant_id', $variant->id);

        $this->assertDatabaseHas('china_commercial_stocks', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'available_quantity' => 5,
        ]);
    }

    public function test_storefront_availability_reads_commercial_stock_after_admin_update(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::chinaPurchasable(28000, 0);
        $product->update([
            'slug' => 'china-admin-stock-product',
            'visibility' => 'public',
        ]);

        $this->patchJson('/api/v1/admin/variants/'.$variant->id.'/commercial-stock', [
            'available_quantity' => 7,
        ])->assertOk();

        $result = app(StockResolver::class)->resolveVariantProduct(
            $variant->fresh(),
            null,
            $product->fresh(['commerceChannel']),
        );

        $this->assertSame(7, $result->quantityAvailable);
        $this->assertSame('china_commercial_stocks', $result->source);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.is_purchasable', true)
            ->assertJsonPath('data.availability_status', 'available')
            ->assertJsonPath('data.variants.0.stock', 7)
            ->assertJsonPath('data.variants.0.in_stock', true);
    }

    public function test_tz_local_product_rejects_commercial_stock_endpoints(): void
    {
        $product = Product::factory()->tzLocal()->create([
            'name' => 'TZ Local Item',
            'slug' => 'tz-local-item',
            'price' => 9000,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'TZ-LOCAL-001',
        ]);

        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 10,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/admin/products/'.$product->id.'/commercial-stock')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commerce_channel']);

        $this->patchJson('/api/v1/admin/products/'.$product->id.'/commercial-stock', [
            'available_quantity' => 5,
        ])->assertStatus(422);

        $this->patchJson('/api/v1/admin/variants/'.$variant->id.'/commercial-stock', [
            'available_quantity' => 5,
        ])->assertStatus(422);

        $this->patchJson('/api/v1/admin/products/'.$product->id.'/stock', [
            'stock_quantity' => 8,
        ])->assertOk();
    }

    public function test_commercial_stock_rejects_negative_quantity(): void
    {
        $product = Product::factory()->fromChina()->create([
            'price' => 1000,
        ]);

        $this->patchJson('/api/v1/admin/products/'.$product->id.'/commercial-stock', [
            'available_quantity' => -1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['available_quantity']);

        $this->assertSame(0, ChinaCommercialStock::query()->where('product_id', $product->id)->count());
    }
}
