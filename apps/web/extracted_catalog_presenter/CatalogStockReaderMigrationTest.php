<?php

namespace Tests\Unit\Services\Inventory;

use App\Enums\CartStatus;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\CustomerProductVariantResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductVariantResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantInventory;
use App\Services\Inventory\CatalogStockPresenter;
use App\Services\Inventory\StockResolver;
use App\Services\ProductConfiguration\ResolveStorefrontConfigurationOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Phase 2A-3C-1 — Catalog/Commerce inventory readers via StockResolver (ADR 055).
 */
class CatalogStockReaderMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_resolver_falls_back_to_legacy_variant_inventory(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'price' => 0,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        Inventory::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 9,
            'reserved_quantity' => 2,
            'low_stock_threshold' => 1,
        ]);

        $result = app(StockResolver::class)->resolveVariantProduct($variant, null, $product);

        $this->assertTrue($result->resolved);
        $this->assertSame('inventory', $result->source);
        $this->assertSame('variant_legacy', $result->inventoryType);
        $this->assertSame(7, $result->quantityAvailable);
        $this->assertTrue($result->meta['legacy_fallback'] ?? false);
    }

    public function test_configuration_options_use_stock_resolver(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'price' => 0,
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'name' => 'Config A',
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 11,
            'reserved' => 1,
            'is_active' => true,
        ]);

        $configs = app(ResolveStorefrontConfigurationOptions::class)->loadConfigurations($product);
        $row = $configs->firstWhere('id', $variant->id);

        $this->assertNotNull($row);
        $this->assertSame(10, $row['stock']);
        $this->assertTrue($row['in_stock']);
    }

    public function test_customer_product_variant_resource_reads_via_resolver(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'price' => 0,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 5,
            'reserved' => 1,
            'is_active' => true,
        ]);
        $variant->setRelation('product', $product);
        $variant->load('inventories');

        $payload = (new CustomerProductVariantResource($variant))
            ->toArray(Request::create('/'));

        $this->assertSame(4, $payload['stock']);
        $this->assertTrue($payload['in_stock']);
        $this->assertSame(4, $payload['inventory']['available_quantity']);
    }

    public function test_cart_item_resource_available_stock_via_resolver(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'price' => 0,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 8,
            'reserved' => 3,
            'is_active' => true,
        ]);
        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'currency' => 'TZS',
        ]);
        $item->setRelation('variant', $variant->load('inventories'));
        $item->setRelation('product', $product);

        $payload = (new CartItemResource($item))->toArray(Request::create('/'));

        $this->assertSame(5, $payload['available_stock']);
    }

    public function test_product_and_variant_resources_use_presenter(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'price' => 25000,
            'visibility' => ProductVisibility::Public,
        ]);
        Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 14, 'reserved_quantity' => 4, 'low_stock_threshold' => 2],
        );
        $product->load('inventory');

        $productPayload = (new ProductResource($product))->toArray(Request::create('/'));
        $this->assertCount(1, $productPayload['inventory']);
        $this->assertSame(10, $productPayload['inventory'][0]['available_quantity']);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 6,
            'reserved' => 1,
            'is_active' => true,
        ]);
        $variant->load(['inventories', 'product']);

        $variantPayload = (new ProductVariantResource($variant))->toArray(Request::create('/'));
        $this->assertSame(5, $variantPayload['inventory']['available_quantity']);
    }

    public function test_catalog_stock_presenter_simple_and_variant(): void
    {
        $presenter = app(CatalogStockPresenter::class);
        $product = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'price' => 10000,
        ]);
        Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => 3, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );

        $this->assertSame(3, $presenter->availableForSimple($product));
        $this->assertSame(3, $presenter->simpleInventoryCollection($product)[0]['available_quantity']);
    }
}
