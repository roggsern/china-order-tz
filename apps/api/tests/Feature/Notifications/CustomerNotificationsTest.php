<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sees_own_notifications(): void
    {
        $user = User::factory()->create();

        Notification::factory()->count(2)->create([
            'user_id' => $user->id,
            'type' => NotificationType::OrderCreated,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'type',
                        'title',
                        'message',
                        'data',
                        'is_read',
                        'read_at',
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_customer_cannot_access_another_customers_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $notification = Notification::factory()->unread()->create([
            'user_id' => $otherUser->id,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertNotFound();
    }

    public function test_unread_count(): void
    {
        $user = User::factory()->create();

        Notification::factory()->count(2)->unread()->create(['user_id' => $user->id]);
        Notification::factory()->create([
            'user_id' => $user->id,
            'read_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_mark_as_read(): void
    {
        $user = User::factory()->create();

        $notification = Notification::factory()->unread()->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_notification_created_after_shipment_update(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $user = User::factory()->create();
        $order = Order::factory()->pending()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Pending,
        ]);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/shipment-status", [
            'shipment_status' => 'PAYMENT_CONFIRMED',
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => NotificationType::ShipmentStatusUpdated->value,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertJsonPath('data.unread_count', 1);
    }

    public function test_guest_rejected(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
    }

    public function test_admin_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
    }
}
