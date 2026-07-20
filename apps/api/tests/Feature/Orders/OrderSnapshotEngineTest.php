<?php

namespace Tests\Feature\Orders;

use App\Enums\CartStatus;
use App\Enums\ShippingMethod;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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

    public function test_guest_cannot_confirm_order(): void
    {
        $this->postJson('/api/v1/orders/confirm')->assertUnauthorized();
    }
}
