<?php

namespace Tests\Feature\CustomerOrders;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerOrderDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_can_view_own_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        OrderItem::factory()->create(['order_id' => $order->id]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.order_number', $order->order_number);
    }

    public function test_authenticated_customer_can_view_own_order_by_order_number(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'COT-000099',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->order_number}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.order_number', 'COT-000099');
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}")->assertNotFound();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $order = Order::factory()->create();

        $this->getJson("/api/v1/orders/{$order->id}")->assertUnauthorized();
    }

    public function test_admin_token_rejected_on_customer_order_details(): void
    {
        $order = Order::factory()->create();

        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson("/api/v1/orders/{$order->id}")->assertUnauthorized();
    }

    public function test_response_includes_order_items(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => 2,
            'unit_price' => 15000,
            'total_price' => 30000,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.items.0.product_id', $item->product_id)
            ->assertJsonPath('data.items.0.product_name', $item->product_name)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_price', '15000.00')
            ->assertJsonPath('data.items.0.subtotal', '30000.00');
    }

    public function test_response_includes_order_summary(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'subtotal' => 100000,
            'shipping_amount' => 5000,
            'discount_amount' => 10000,
            'total' => 95000,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.summary.subtotal', '100000.00')
            ->assertJsonPath('data.summary.shipping', '5000.00')
            ->assertJsonPath('data.summary.discount', '10000.00')
            ->assertJsonPath('data.summary.total', '95000.00');
    }

    public function test_response_includes_payment_information(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::Mpesa,
            'status' => PaymentStatus::Pending,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.payment.payment_status', PaymentStatus::Pending->value)
            ->assertJsonPath('data.payment.payment_method', PaymentMethod::Mpesa->value);
    }

    public function test_response_includes_shipment_placeholder(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.shipment.status', 'Preparing');
    }
}
