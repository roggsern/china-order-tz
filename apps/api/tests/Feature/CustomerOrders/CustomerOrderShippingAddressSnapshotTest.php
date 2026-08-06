<?php

namespace Tests\Feature\CustomerOrders;

use App\Enums\CartStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryAddress;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\Orders\OrderShippingAddressSnapshotService;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerOrderShippingAddressSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_order_creates_shipping_snapshot_from_saved_address_book_only(): void
    {
        $user = User::factory()->create(['email' => 'book-only@example.com']);
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(22000);

        $this->seedCart($user, $product->id, $variant->id, 1, 22000);

        UserAddress::factory()->default()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Book Only Customer',
            'phone' => '+255712345679',
            'address_line_1' => 'Address Book Street',
            'address_line_2' => 'Ubungo',
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
            'country' => 'Tanzania',
        ]);

        $this->assertNull(DeliveryAddress::query()->where('user_id', $user->id)->first());

        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->postJson("/api/v1/checkout/{$sessionId}/shipping-choice", [
            'shipping_choice' => 'self_pickup',
        ])->assertOk();

        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('shipping_addresses', [
            'order_id' => $orderId,
            'user_id' => $user->id,
            'first_name' => 'Book',
            'last_name' => 'Only Customer',
            'phone' => '+255712345679',
            'address_line_1' => 'Address Book Street',
            'address_line_2' => 'Ubungo',
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
            'country' => 'Tanzania',
        ]);
    }

    public function test_order_shipping_snapshot_uses_recipient_not_account_identity(): void
    {
        $user = User::factory()->create([
            'email' => 'robert.identity@example.com',
            'first_name' => 'Robert',
            'last_name' => 'Musa',
            'name' => 'Robert Musa',
        ]);
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(22000);

        $this->seedCart($user, $product->id, $variant->id, 1, 22000);

        UserAddress::factory()->default()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Mama Asha',
            'phone' => '+255700000099',
            'address_line_1' => 'Recipient Street',
            'address_line_2' => 'Ilala',
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
            'country' => 'Tanzania',
        ]);

        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->postJson("/api/v1/checkout/{$sessionId}/shipping-choice", [
            'shipping_choice' => 'self_pickup',
        ])->assertOk();

        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('shipping_addresses', [
            'order_id' => $orderId,
            'first_name' => 'Mama',
            'last_name' => 'Asha',
            'phone' => '+255700000099',
            'address_line_1' => 'Recipient Street',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Robert',
            'last_name' => 'Musa',
            'name' => 'Robert Musa',
        ]);
    }

    public function test_phone_only_profile_sync_before_order_does_not_require_payment_to_preserve_identity(): void
    {
        $user = User::factory()->create([
            'email' => 'robert.preserve@example.com',
            'first_name' => 'Robert',
            'last_name' => 'Musa',
            'name' => 'Robert Musa',
            'phone' => '+255711111111',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile', [
            'phone' => '+255733333333',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Robert',
            'last_name' => 'Musa',
            'name' => 'Robert Musa',
            'phone' => '+255733333333',
        ]);
    }

    public function test_checkout_without_any_address_is_rejected(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(15000);

        $this->seedCart($user, $product->id, $variant->id, 1, 15000);

        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->postJson("/api/v1/checkout/{$sessionId}/shipping-choice", [
            'shipping_choice' => 'self_pickup',
        ])->assertOk();

        $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_address']);
    }

    public function test_checkout_order_creates_shipping_address_snapshot(): void
    {
        $user = User::factory()->create(['email' => 'customer@example.com']);
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(25000);

        $this->seedCart($user, $product->id, $variant->id, 1, 25000);

        DeliveryAddress::factory()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Rogson Joseph Malumbu',
            'phone' => '+255712345678',
            'street' => 'Plot 88 Kariakoo Street',
            'district' => 'Ilala',
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
            'country' => 'Tanzania',
            'landmark' => 'Near clock tower',
            'postal_code' => '14111',
        ]);

        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId, [
            'shipping_choice' => 'self_pickup',
        ]);

        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('shipping_addresses', [
            'order_id' => $orderId,
            'user_id' => $user->id,
            'first_name' => 'Rogson',
            'last_name' => 'Joseph Malumbu',
            'phone' => '+255712345678',
            'email' => 'customer@example.com',
            'address_line_1' => 'Plot 88 Kariakoo Street',
            'address_line_2' => 'Ilala · Near clock tower',
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
            'postal_code' => '14111',
            'country' => 'Tanzania',
            'is_default' => false,
        ]);

        $this->assertSame(
            1,
            ShippingAddress::query()->where('order_id', $orderId)->count(),
        );
    }

    public function test_customer_order_detail_returns_shipping_address(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(18000);

        $this->seedCart($user, $product->id, $variant->id, 1, 18000);

        DeliveryAddress::factory()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Jane Customer',
            'phone' => '+255700000001',
            'street' => 'Sam Nujoma Road',
            'district' => 'Kinondoni',
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
            'country' => 'Tanzania',
            'landmark' => null,
        ]);

        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId, [
            'shipping_choice' => 'self_pickup',
        ]);

        $orderNumber = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->json('data.order_number');

        $this->getJson("/api/v1/orders/{$orderNumber}")
            ->assertOk()
            ->assertJsonPath('data.shipping_address.address_line_1', 'Sam Nujoma Road')
            ->assertJsonPath('data.shipping_address.address_line_2', 'Kinondoni')
            ->assertJsonPath('data.shipping_address.city', 'Dar es Salaam')
            ->assertJsonPath('data.shipping_address.region', 'Dar es Salaam')
            ->assertJsonPath('data.shipping_address.country', 'Tanzania')
            ->assertJsonPath('data.shipping_address.phone', '+255700000001')
            ->assertJsonPath('data.shipping_address.full_name', 'Jane Customer');
    }

    public function test_snapshot_service_syncs_from_saved_address_book_when_delivery_profile_missing(): void
    {
        $user = User::factory()->create(['email' => 'snapshot-fallback@example.com']);
        $order = Order::factory()->create(['user_id' => $user->id]);

        UserAddress::factory()->default()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Fallback Customer',
            'phone' => '+255712340000',
            'address_line_1' => 'Fallback Street',
            'address_line_2' => 'Temeke',
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
            'country' => 'Tanzania',
        ]);

        $this->assertNull(DeliveryAddress::query()->where('user_id', $user->id)->first());

        $service = app(OrderShippingAddressSnapshotService::class);

        $snapshot = $service->ensureFromDeliveryAddress($order, $user);

        $this->assertNotNull($snapshot);
        $this->assertDatabaseHas('shipping_addresses', [
            'id' => $snapshot?->id,
            'order_id' => $order->id,
            'address_line_1' => 'Fallback Street',
            'address_line_2' => 'Temeke',
            'first_name' => 'Fallback',
            'last_name' => 'Customer',
        ]);
    }

    public function test_shipping_address_snapshot_is_idempotent(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        DeliveryAddress::factory()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Repeat Customer',
            'phone' => '+255711111111',
            'street' => 'Repeat Street',
            'district' => 'Ilala',
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
            'country' => 'Tanzania',
        ]);

        $service = app(OrderShippingAddressSnapshotService::class);

        $first = $service->ensureFromDeliveryAddress($order, $user);
        $second = $service->ensureFromDeliveryAddress($order->fresh(['shippingAddress']), $user);

        $this->assertNotNull($first);
        $this->assertSame($first?->id, $second?->id);
        $this->assertSame(
            1,
            ShippingAddress::query()->where('order_id', $order->id)->count(),
        );
    }

    private function seedCart(
        User $user,
        string $productId,
        string $variantId,
        int $quantity,
        float $unitPrice,
    ): Cart {
        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'price_snapshot' => $unitPrice,
            'currency' => 'TZS',
        ]);

        return $cart;
    }
}
