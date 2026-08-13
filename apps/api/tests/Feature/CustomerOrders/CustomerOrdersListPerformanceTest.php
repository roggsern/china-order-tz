<?php

namespace Tests\Feature\CustomerOrders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerOrdersListPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_returns_preview_from_order_item_snapshots_only(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'COTZ-20260730-000001',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name_snapshot' => 'Tie-Front Blouse',
            'product_image_snapshot' => 'https://cdn.example/blouse.jpg',
            'quantity' => 2,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name_snapshot' => 'Silk Scarf',
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.preview.item_count', 2)
            ->assertJsonPath('data.0.preview.total_quantity', 3)
            ->assertJsonPath('data.0.preview.extra_items', 1)
            ->assertJsonStructure([
                'data' => [
                    [
                        'preview' => [
                            'item_count',
                            'total_quantity',
                            'primary_item' => [
                                'name',
                                'image_url',
                                'quantity',
                            ],
                            'extra_items',
                        ],
                    ],
                ],
            ]);

        $primaryName = $response->json('data.0.preview.primary_item.name');
        $this->assertContains($primaryName, ['Tie-Front Blouse', 'Silk Scarf']);
    }

    public function test_list_payment_status_uses_latest_payment_record(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => PaymentStatus::Initiated,
            'created_at' => now()->subMinute(),
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => PaymentStatus::Pending,
            'created_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.0.payment_status', PaymentStatus::Initiated->value);
    }

    public function test_list_payment_status_uses_paid_at_when_no_payment_row_exists(): void
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.0.payment_status', PaymentStatus::Paid->value);
    }

    public function test_list_includes_progress_and_receiving_choice_for_order_cards(): void
    {
        $user = User::factory()->create();

        Order::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    [
                        'progress',
                        'receiving_choice',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_order_detail_flow_remains_available_with_full_items_payload(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'COTZ-20260730-000099',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name_snapshot' => 'Detail Snapshot Product',
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/orders/'.$order->order_number)
            ->assertOk()
            ->assertJsonPath('data.order_number', 'COTZ-20260730-000099')
            ->assertJsonPath('data.items.0.product_name', 'Detail Snapshot Product')
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'summary',
                    'payment',
                    'progress',
                ],
            ]);
    }
}
