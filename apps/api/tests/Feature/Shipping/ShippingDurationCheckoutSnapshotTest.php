<?php

namespace Tests\Feature\Shipping;

use App\Enums\CartStatus;
use App\Enums\ShippingMethod;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryAddress;
use App\Models\OrderItem;
use App\Models\ProductShippingOption;
use App\Models\ShippingMethod as ShippingMethodModel;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\Orders\OrderSnapshotEngine;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShippingDurationCheckoutSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function seedRate(string $code, int $min, int $max, int $typical, string $fulfillment): void
    {
        $method = ShippingMethodModel::query()->create([
            'code' => $code,
            'name' => $code,
            'fulfillment_source' => $fulfillment,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'base_cost' => 1000,
            'currency' => 'TZS',
            'is_active' => true,
            'estimated_delivery_days' => $typical,
            'estimated_min_days' => $min,
            'estimated_max_days' => $max,
        ]);
    }

    private function seedChinaCart(User $user, ShippingMethod $mode = ShippingMethod::Air): array
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(100000);
        $product->update(['fulfillment_source' => 'imported_from_china']);

        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();
        if ($mode === ShippingMethod::Sea) {
            ProductShippingOption::factory()->sea(3000)->create(['product_id' => $product->id]);
        } else {
            ProductShippingOption::factory()->air(8000)->create(['product_id' => $product->id]);
        }

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
            'unit_price' => 100000,
            'price_snapshot' => 100000,
            'currency' => 'TZS',
            'shipping_method' => $mode,
            'shipping_price' => $mode === ShippingMethod::Sea ? 3000 : 8000,
        ]);

        return compact('product', 'variant', 'cart', 'item');
    }

    public function test_checkout_shipping_choice_captures_duration_on_cart_item(): void
    {
        $this->seedRate('air_freight', 7, 12, 10, 'imported_from_china');

        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);
        $this->seedChinaCart($user, ShippingMethod::Air);

        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/v1/checkout/start')
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/checkout/{$sessionId}/shipping-choice", [
            'shipping_choice' => 'company_shipping',
            'shipping_method' => 'air',
        ])->assertOk();

        $item = CartItem::query()->firstOrFail();
        $this->assertSame(7, $item->estimated_min_days);
        $this->assertSame(12, $item->estimated_max_days);
        $this->assertSame(10, $item->estimated_delivery_days);
    }

    public function test_order_item_snapshot_stores_duration_window(): void
    {
        $this->seedRate('air_freight', 7, 12, 10, 'imported_from_china');

        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);
        $this->seedChinaCart($user, ShippingMethod::Air);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders/confirm', [
            'shipping_choice' => 'company_shipping',
            'shipping_method' => 'air',
        ])->assertCreated();

        $orderId = $response->json('data.order.id') ?? $response->json('data.id');
        $item = OrderItem::query()->where('order_id', $orderId)->firstOrFail();

        $this->assertSame(7, (int) $item->estimated_min_days_snapshot);
        $this->assertSame(12, (int) $item->estimated_max_days_snapshot);
        $this->assertSame(10, (int) $item->estimated_delivery_days);

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.estimated_min_days_snapshot', 7)
            ->assertJsonPath('data.items.0.estimated_max_days_snapshot', 12);
    }

    public function test_changing_shipping_rates_does_not_affect_old_order_snapshots(): void
    {
        $this->seedRate('sea_freight', 35, 45, 40, 'imported_from_china');

        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);
        $this->seedChinaCart($user, ShippingMethod::Sea);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders/confirm', [
            'shipping_choice' => 'company_shipping',
            'shipping_method' => 'sea',
        ])->assertCreated();

        $orderId = $response->json('data.order.id') ?? $response->json('data.id');
        $item = OrderItem::query()->where('order_id', $orderId)->firstOrFail();
        $this->assertSame(35, (int) $item->estimated_min_days_snapshot);
        $this->assertSame(45, (int) $item->estimated_max_days_snapshot);

        ShippingRate::query()->update([
            'estimated_min_days' => 90,
            'estimated_max_days' => 120,
            'estimated_delivery_days' => 100,
        ]);

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.estimated_min_days_snapshot', 35)
            ->assertJsonPath('data.items.0.estimated_max_days_snapshot', 45);

        $this->assertSame(
            35,
            (int) OrderItem::query()->where('order_id', $orderId)->value('estimated_min_days_snapshot'),
        );
    }

    public function test_snapshot_engine_prefers_cart_captured_duration(): void
    {
        $user = User::factory()->create();
        ['item' => $item] = $this->seedChinaCart($user, ShippingMethod::Air);

        $item->forceFill([
            'estimated_min_days' => 9,
            'estimated_max_days' => 11,
            'estimated_delivery_days' => 10,
        ])->save();

        $payload = app(OrderSnapshotEngine::class)->snapshotFromCartItem($item->fresh(['product', 'variant']));

        $this->assertSame(9, $payload['estimated_min_days_snapshot']);
        $this->assertSame(11, $payload['estimated_max_days_snapshot']);
        $this->assertSame(10, $payload['estimated_delivery_days']);
    }
}
