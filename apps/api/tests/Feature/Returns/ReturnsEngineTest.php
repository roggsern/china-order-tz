<?php

namespace Tests\Feature\Returns;

use App\Enums\ActivityEventType;
use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Enums\RefundTransactionStatus;
use App\Enums\ReturnRequestStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Events\Returns\ReturnApproved;
use App\Events\Returns\ReturnRequested;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Fulfillment;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReturnsEngineTest extends TestCase
{
    use RefreshDatabase;

    private function deliveredOrderFor(User $user): array
    {
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
            'paid_at' => now()->subDays(5),
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => 2,
            'unit_price' => 40000,
            'unit_price_snapshot' => 40000,
            'line_total' => 80000,
            'total_price' => 80000,
        ]);

        Fulfillment::factory()->create(['order_id' => $order->id]);
        Shipment::factory()->create([
            'order_id' => $order->id,
            'status' => ShipmentLifecycleStatus::Delivered,
            'delivered_at' => now()->subDay(),
        ]);

        return compact('order', 'item');
    }

    public function test_return_eligibility_rejects_pending_payment(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment,
        ]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/returns", [
            'reason' => 'Changed',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_customer_can_create_return_for_delivered_order(): void
    {
        Event::fake([ReturnRequested::class]);

        $user = User::factory()->create();
        ['order' => $order, 'item' => $item] = $this->deliveredOrderFor($user);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/returns", [
            'reason' => 'Damaged item',
            'description' => 'Box crushed',
            'items' => [
                [
                    'order_item_id' => $item->id,
                    'quantity' => 1,
                    'replacement_requested' => false,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'requested');

        Event::assertDispatched(ReturnRequested::class);
        $this->assertDatabaseHas('return_requests', [
            'order_id' => $order->id,
            'customer_id' => $user->id,
            'status' => 'requested',
        ]);
    }

    public function test_approval_and_reject_flow(): void
    {
        $user = User::factory()->create();
        ['order' => $order, 'item' => $item] = $this->deliveredOrderFor($user);
        Sanctum::actingAs($user);

        $returnId = $this->postJson("/api/v1/orders/{$order->id}/returns", [
            'reason' => 'Wrong size',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->json('data.id');

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        Event::fake([ReturnApproved::class]);

        $this->patchJson("/api/v1/admin/returns/{$returnId}/status", [
            'status' => 'approved',
            'admin_notes' => 'OK to proceed',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        Event::assertDispatched(ReturnApproved::class);

        // Separate return for reject path
        $order2 = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
            'paid_at' => now(),
        ]);
        $item2 = OrderItem::factory()->create(['order_id' => $order2->id, 'quantity' => 1]);
        Fulfillment::factory()->create(['order_id' => $order2->id]);
        Shipment::factory()->create([
            'order_id' => $order2->id,
            'status' => ShipmentLifecycleStatus::Delivered,
            'delivered_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $rejectId = $this->postJson("/api/v1/orders/{$order2->id}/returns", [
            'reason' => 'Changed',
            'items' => [['order_item_id' => $item2->id, 'quantity' => 1]],
        ])->json('data.id');

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/returns/{$rejectId}/status", [
            'status' => 'rejected',
            'admin_notes' => 'Outside window',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');
    }

    public function test_refund_flow_is_manual(): void
    {
        $user = User::factory()->create();
        ['order' => $order, 'item' => $item] = $this->deliveredOrderFor($user);
        Sanctum::actingAs($user);

        $returnId = $this->postJson("/api/v1/orders/{$order->id}/returns", [
            'reason' => 'Damaged',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->json('data.id');

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/returns/{$returnId}/status", [
            'status' => 'approved',
        ])->assertOk();

        // Creating refund without auto-complete stays pending unless status path requested
        $refund = $this->postJson("/api/v1/admin/returns/{$returnId}/refund", [
            'amount' => 40000,
            'method' => 'manual',
        ])->assertCreated()->json('data');

        $this->assertSame('pending', $refund['status']);

        $completed = $this->postJson("/api/v1/admin/returns/{$returnId}/refund", [
            'amount' => 1000,
        ]);
        // Open refund already exists
        $completed->assertStatus(422);

        // Advance via creating on another return, or update by creating with status on fresh return
        $order3 = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
            'paid_at' => now(),
        ]);
        $item3 = OrderItem::factory()->create([
            'order_id' => $order3->id,
            'quantity' => 1,
            'unit_price_snapshot' => 30000,
        ]);
        Fulfillment::factory()->create(['order_id' => $order3->id]);
        Shipment::factory()->create([
            'order_id' => $order3->id,
            'status' => ShipmentLifecycleStatus::Delivered,
            'delivered_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $return3 = $this->postJson("/api/v1/orders/{$order3->id}/returns", [
            'reason' => 'Damaged',
            'items' => [['order_item_id' => $item3->id, 'quantity' => 1]],
        ])->json('data.id');

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/returns/{$return3}/status", ['status' => 'approved'])->assertOk();

        $this->postJson("/api/v1/admin/returns/{$return3}/refund", [
            'amount' => 30000,
            'status' => 'completed',
        ])->assertCreated()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('refund_transactions', [
            'return_request_id' => $return3,
            'status' => RefundTransactionStatus::Completed->value,
        ]);
    }

    public function test_authorization(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        ['order' => $order, 'item' => $item] = $this->deliveredOrderFor($user);

        Sanctum::actingAs($user);
        $returnId = $this->postJson("/api/v1/orders/{$order->id}/returns", [
            'reason' => 'Damaged',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->json('data.id');

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/returns/{$returnId}")->assertNotFound();

        $this->getJson('/api/v1/admin/returns')->assertUnauthorized();

        Sanctum::actingAs(Admin::factory()->create());
        $this->getJson('/api/v1/admin/returns')->assertOk();
    }

    public function test_notifications_and_audit_on_return_request(): void
    {
        $user = User::factory()->create(['name' => 'Asha']);
        ['order' => $order, 'item' => $item] = $this->deliveredOrderFor($user);

        // Seed minimal template
        \App\Models\NotificationTemplate::factory()->create([
            'key' => 'return_requested.in_app',
            'channel' => 'in_app',
            'body' => 'Hello {{customer_name}}, return for {{order_number}}.',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/returns", [
            'reason' => 'Damaged',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'customer_id' => $user->id,
            'event_type' => NotificationEventType::ReturnRequested->value,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::ReturnRequested->value,
            'actor_id' => $user->id,
        ]);
    }

    public function test_customer_lists_returns(): void
    {
        $user = User::factory()->create();
        ['order' => $order, 'item' => $item] = $this->deliveredOrderFor($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/returns", [
            'reason' => 'Damaged',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->getJson('/api/v1/returns')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_full_status_path_to_completed(): void
    {
        $user = User::factory()->create();
        ['order' => $order, 'item' => $item] = $this->deliveredOrderFor($user);
        Sanctum::actingAs($user);
        $returnId = $this->postJson("/api/v1/orders/{$order->id}/returns", [
            'reason' => 'Damaged',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->json('data.id');

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/returns/{$returnId}/status", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/v1/admin/returns/{$returnId}/status", ['status' => 'inspection'])->assertOk();
        $this->patchJson("/api/v1/admin/returns/{$returnId}/status", [
            'status' => 'completed',
            'items' => [[
                'id' => \App\Models\ReturnItem::query()->where('return_request_id', $returnId)->value('id'),
                'condition' => 'used',
                'resolution' => 'refund',
                'refund_amount' => 35000,
                'inventory_disposition' => 'no_restock',
            ]],
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::ReturnCompleted->value,
        ]);
    }
}
