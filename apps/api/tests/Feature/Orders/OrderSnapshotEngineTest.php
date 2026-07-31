<?php

namespace Tests\Feature\Orders;

use App\Enums\CartStatus;
use App\Enums\ShippingMethod;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\DeliveryAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductMedia;
use App\Models\ProductShippingOption;
use App\Models\User;
use App\Services\Orders\OrderSnapshotEngine;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderSnapshotEngineTest extends TestCase
{
    use RefreshDatabase;

    private function seedChinaCart(User $user, array $productOverrides = [], array $itemOverrides = []): array
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(
            (float) ($productOverrides['price'] ?? 100000),
        );

        $product->update(array_merge([
            'fulfillment_source' => 'imported_from_china',
            'name' => $productOverrides['name'] ?? $product->name,
            'slug' => $productOverrides['slug'] ?? $product->slug,
        ], $productOverrides));
        $product->refresh();

        $itemDefaults = [
            'quantity' => 2,
            'unit_price' => (float) ($productOverrides['price'] ?? 100000),
            'price_snapshot' => (float) ($productOverrides['price'] ?? 100000),
            'currency' => 'TZS',
            'shipping_method' => ShippingMethod::Air,
            'shipping_price' => 8000,
        ];
        $itemData = array_merge($itemDefaults, $itemOverrides);

        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();
        $mode = $itemData['shipping_method'] instanceof ShippingMethod
            ? $itemData['shipping_method']
            : ShippingMethod::tryFrom((string) $itemData['shipping_method']);

        if ($mode === ShippingMethod::Sea) {
            ProductShippingOption::factory()->sea((float) $itemData['shipping_price'])->create([
                'product_id' => $product->id,
                'notes' => $productOverrides['shipping_notes'] ?? 'Sea notes',
            ]);
        } else {
            ProductShippingOption::factory()->air((float) $itemData['shipping_price'])->create([
                'product_id' => $product->id,
                'notes' => $productOverrides['shipping_notes'] ?? 'Express air rate',
            ]);
        }

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create(array_merge($itemData, [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
        ]));

        return compact('product', 'variant', 'cart');
    }

    public function test_checkout_populates_immutable_snapshots(): void
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);

        $this->seedChinaCart($user, [
            'name' => 'Original Phone',
            'slug' => 'original-phone',
            'price' => 100000,
            'shipping_notes' => 'Express air rate',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders/confirm', [
            'shipping_choice' => 'company_shipping',
            'shipping_method' => 'air',
        ])->assertCreated();
        $orderId = $response->json('data.order.id') ?? $response->json('data.id');

        $item = OrderItem::query()->where('order_id', $orderId)->first();
        $this->assertNotNull($item);
        $this->assertSame('Original Phone', $item->getAttributes()['product_name_snapshot'] ?? $item->product_name_snapshot);
        $this->assertSame('original-phone', $item->product_slug_snapshot);
        $this->assertSame(100000.0, (float) $item->getRawOriginal('unit_price_snapshot'));
        $this->assertSame('air', $item->getRawOriginal('shipping_mode_snapshot'));
        $this->assertSame(8000.0, (float) $item->getRawOriginal('shipping_price_snapshot'));
        $this->assertSame('Express air rate', $item->shipping_notes_snapshot);
        $this->assertSame('TZS', $item->getRawOriginal('currency_snapshot'));
    }

    public function test_catalog_price_change_does_not_affect_order(): void
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);

        ['product' => $product] = $this->seedChinaCart($user, [
            'name' => 'Stable Name',
            'price' => 50000,
        ], [
            'quantity' => 1,
            'unit_price' => 50000,
            'price_snapshot' => 50000,
            'shipping_method' => ShippingMethod::Sea,
            'shipping_price' => 3000,
        ]);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/orders/confirm', [
            'shipping_choice' => 'company_shipping',
            'shipping_method' => 'sea',
        ])->assertCreated();
        $orderId = $response->json('data.order.id') ?? $response->json('data.id');

        $item = OrderItem::query()->where('order_id', $orderId)->firstOrFail();
        $this->assertSame(50000.0, (float) $item->getRawOriginal('unit_price_snapshot'));

        $product->update(['price' => 999999, 'name' => 'Changed Name']);

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name_snapshot', 'Stable Name');

        $this->assertSame(
            50000.0,
            (float) OrderItem::query()->where('order_id', $orderId)->value('unit_price_snapshot'),
        );
    }

    public function test_shipping_price_change_does_not_affect_order(): void
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);

        ['product' => $product] = $this->seedChinaCart($user, [
            'price' => 25000,
        ], [
            'quantity' => 1,
            'unit_price' => 25000,
            'price_snapshot' => 25000,
            'shipping_method' => ShippingMethod::Air,
            'shipping_price' => 7000,
        ]);

        $option = ProductShippingOption::query()->where('product_id', $product->id)->firstOrFail();

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/orders/confirm', [
            'shipping_choice' => 'company_shipping',
            'shipping_method' => 'air',
        ])->assertCreated();
        $orderId = $response->json('data.order.id') ?? $response->json('data.id');

        $option->update(['price' => 70000]);
        $product->update(['air_shipping_price' => 70000]);

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.shipping_mode_snapshot', 'air');

        $this->assertSame(
            7000.0,
            (float) OrderItem::query()->where('order_id', $orderId)->value('shipping_price_snapshot'),
        );
    }

    public function test_deleted_product_still_displays_historical_order(): void
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);

        ['product' => $product] = $this->seedChinaCart($user, [
            'name' => 'Doomed Product',
            'price' => 25000,
        ], [
            'quantity' => 1,
            'unit_price' => 25000,
            'price_snapshot' => 25000,
            'shipping_method' => ShippingMethod::Sea,
            'shipping_price' => 2500,
        ]);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/orders/confirm', [
            'shipping_choice' => 'company_shipping',
            'shipping_method' => 'sea',
        ])->assertCreated();
        $orderId = $response->json('data.order.id') ?? $response->json('data.id');

        $product->delete();

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name_snapshot', 'Doomed Product')
            ->assertJsonPath('data.items.0.shipping_mode_snapshot', 'sea');
    }

    public function test_snapshot_fields_are_immutable(): void
    {
        $item = OrderItem::factory()->create([
            'product_name_snapshot' => 'Frozen',
            'unit_price_snapshot' => 1000,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $item->update(['product_name_snapshot' => 'Mutated']);
    }

    public function test_relationships_remain_valid(): void
    {
        $order = Order::factory()->create();
        $product = Product::factory()->create();
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $this->assertTrue($item->order()->is($order));
        $this->assertTrue($item->product()->is($product));
        $this->assertTrue($order->items()->whereKey($item->id)->exists());
    }

    public function test_engine_reads_catalog_variant_and_shipping(): void
    {
        $product = Product::factory()->fromChina()->create(['name' => 'Engine Phone']);
        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();
        ProductShippingOption::factory()->sea(4000)->create([
            'product_id' => $product->id,
            'notes' => 'Sea notes',
        ]);

        $cart = Cart::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 20000,
            'price_snapshot' => 20000,
            'shipping_method' => ShippingMethod::Sea,
            'shipping_price' => 4000,
        ]);

        $payload = app(OrderSnapshotEngine::class)->snapshotFromCartItem($item);

        $this->assertSame('Engine Phone', $payload['product_name_snapshot']);
        $this->assertSame('sea', $payload['shipping_mode_snapshot']);
        $this->assertSame('4000.00', (string) $payload['shipping_price_snapshot']);
        $this->assertSame('Sea notes', $payload['shipping_notes_snapshot']);
        $this->assertSame('60000.00', (string) $payload['line_total']);
        $this->assertSame('12000.00', (string) $payload['shipping_subtotal']);
    }

    public function test_snapshot_prefers_variant_media_image(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(50000);

        ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'url' => '/storage/product-primary.jpg',
        ]);
        ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'url' => '/storage/variant-primary.jpg',
        ]);

        $cart = Cart::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'price_snapshot' => 50000,
        ]);

        $payload = app(OrderSnapshotEngine::class)->snapshotFromCartItem($item->fresh());

        $this->assertSame('variant-primary.jpg', $payload['image_snapshot']);
        $this->assertSame('variant-primary.jpg', $payload['product_image_snapshot']);
    }

    public function test_catalog_attributes_are_snapshotted(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(50000);

        $color = CatalogAttribute::factory()->create(['name' => 'Color', 'slug' => 'color-snap']);
        $black = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'Black',
            'slug' => 'black-snap',
        ]);
        $variant->catalogAttributeValues()->create([
            'catalog_attribute_id' => $color->id,
            'option_id' => $black->id,
            'value_text' => 'Black',
        ]);

        $legacyAttribute = ProductAttribute::factory()->create(['name' => 'Legacy Color']);
        $legacyValue = ProductAttributeValue::factory()->create([
            'product_attribute_id' => $legacyAttribute->id,
            'value' => 'Red',
        ]);
        $variant->attributeValues()->sync([$legacyValue->id]);

        $cart = Cart::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'price_snapshot' => 50000,
        ]);

        $payload = app(OrderSnapshotEngine::class)->snapshotFromCartItem($item->fresh());

        $this->assertSame([
            ['attribute' => 'Color', 'value' => 'Black'],
        ], $payload['attributes_snapshot']);
    }

    public function test_barcode_snapshot_is_populated(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(50000);
        $variant->update(['barcode' => 'BC-ORDER-12345']);

        $cart = Cart::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'price_snapshot' => 50000,
        ]);

        $payload = app(OrderSnapshotEngine::class)->snapshotFromCartItem($item->fresh());

        $this->assertSame('BC-ORDER-12345', $payload['barcode_snapshot']);
    }

    public function test_deleted_variant_still_displays_historical_order(): void
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);

        ['product' => $product, 'variant' => $variant] = $this->seedChinaCart($user, [
            'name' => 'Variant History Phone',
            'price' => 25000,
        ], [
            'quantity' => 1,
            'unit_price' => 25000,
            'price_snapshot' => 25000,
            'shipping_method' => ShippingMethod::Sea,
            'shipping_price' => 2500,
        ]);

        $variant->update([
            'name' => 'Black / 128GB',
            'sku' => 'HIST-VAR-128',
            'barcode' => 'BC-HIST-999',
        ]);

        ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'url' => '/storage/hist-variant.jpg',
        ]);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/orders/confirm', [
            'shipping_choice' => 'company_shipping',
            'shipping_method' => 'sea',
        ])->assertCreated();
        $orderId = $response->json('data.order.id') ?? $response->json('data.id');

        $item = OrderItem::query()->where('order_id', $orderId)->firstOrFail();
        $this->assertSame('Black / 128GB', $item->variant_name_snapshot);
        $this->assertSame('HIST-VAR-128', $item->variant_sku_snapshot);
        $this->assertSame('BC-HIST-999', $item->barcode_snapshot);
        $this->assertSame('hist-variant.jpg', $item->product_image_snapshot ?? $item->image_snapshot);

        $variant->delete();
        $product->update(['name' => 'Live Name Changed']);
        ProductMedia::query()->where('product_id', $product->id)->delete();

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name_snapshot', 'Variant History Phone')
            ->assertJsonPath('data.items.0.variant_name_snapshot', 'Black / 128GB')
            ->assertJsonPath('data.items.0.variant_sku_snapshot', 'HIST-VAR-128')
            ->assertJsonPath('data.items.0.barcode_snapshot', 'BC-HIST-999')
            ->assertJsonPath('data.items.0.product_image_snapshot', 'hist-variant.jpg');
    }

    public function test_image_snapshot_survives_live_media_deletion(): void
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);

        ['product' => $product, 'variant' => $variant] = $this->seedChinaCart($user, [
            'name' => 'Image Snap Phone',
            'price' => 30000,
        ], [
            'quantity' => 1,
            'unit_price' => 30000,
            'price_snapshot' => 30000,
            'shipping_method' => ShippingMethod::Air,
            'shipping_price' => 5000,
        ]);

        ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'url' => '/storage/order-image-snap.jpg',
        ]);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/orders/confirm', [
            'shipping_choice' => 'company_shipping',
            'shipping_method' => 'air',
        ])->assertCreated();
        $orderId = $response->json('data.order.id') ?? $response->json('data.id');

        ProductMedia::query()->where('product_id', $product->id)->delete();

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.product_image_snapshot', 'order-image-snap.jpg')
            ->assertJsonPath('data.items.0.image_snapshot', 'order-image-snap.jpg');
    }

    public function test_guest_cannot_confirm_order(): void
    {
        $this->postJson('/api/v1/orders/confirm')->assertUnauthorized();
    }
}
