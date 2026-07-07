<?php

namespace Tests\Feature\CustomerOrders;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerShipmentTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_can_view_shipment_tracking(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}/tracking");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_number', $order->order_number);
    }

    public function test_customer_cannot_view_another_customers_shipment(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}/tracking")->assertNotFound();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $order = Order::factory()->create();

        $this->getJson("/api/v1/orders/{$order->id}/tracking")->assertUnauthorized();
    }

    public function test_admin_token_rejected_on_customer_shipment_tracking(): void
    {
        $order = Order::factory()->create();

        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson("/api/v1/orders/{$order->id}/tracking")->assertUnauthorized();
    }

    public function test_timeline_structure_is_correct(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}/tracking");

        $response->assertOk()
            ->assertJsonCount(count(ShipmentStatus::timeline()), 'data.timeline')
            ->assertJsonStructure([
                'data' => [
                    'order_number',
                    'current_status',
                    'timeline' => [
                        [
                            'step',
                            'completed',
                            'completed_at',
                            'description',
                        ],
                    ],
                ],
            ]);
    }

    public function test_current_status_is_returned(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}/tracking");

        $response->assertOk()
            ->assertJsonPath('data.current_status', ShipmentStatus::Delivered->value);
    }

    public function test_descriptions_are_present(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}/tracking");

        $response->assertOk();

        foreach ($response->json('data.timeline') as $step) {
            $this->assertNotEmpty($step['description']);
            $this->assertNotEmpty($step['step']);
        }
    }
}
