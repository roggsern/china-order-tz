<?php

namespace Tests\Feature\Tracking;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Enums\TimelineVisibility;
use App\Enums\TrackingEventType;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\OrderTrackingEvent;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Tracking\TrackingEngine;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Launch Closure #5 — Tracking & Notifications.
 */
class TrackingNotificationClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_unified_customer_timeline_filters_internal_events(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'previous_status' => null,
            'new_status' => OrderStatus::PendingPayment->value,
            'source' => 'order_engine',
            'idempotency_key' => 'lifecycle-create:'.$order->id,
        ]);
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'previous_status' => OrderStatus::PendingPayment->value,
            'new_status' => OrderStatus::Paid->value,
            'source' => 'payment',
            'idempotency_key' => 'lifecycle-paid:'.$order->id,
        ]);

        $recordId = (string) Str::uuid();
        DB::table('china_workflow_records')->insert([
            'id' => $recordId,
            'order_id' => $order->id,
            'fulfillment_id' => null,
            'stage' => 'qc_pending',
            'qc_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('china_workflow_histories')->insert([
            'id' => (string) Str::uuid(),
            'china_workflow_record_id' => $recordId,
            'order_id' => $order->id,
            'admin_id' => null,
            'action' => 'qc_failed',
            'from_stage' => 'qc_pending',
            'to_stage' => 'qc_failed',
            'reason' => 'INTERNAL DAMAGE NOTES',
            'metadata' => null,
            'idempotency_key' => 'china-qc-fail:'.$order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customer = app(TrackingEngine::class)->composeOrderTimeline($order->fresh(), TimelineVisibility::Customer);
        $internal = app(TrackingEngine::class)->composeOrderTimeline($order->fresh(), TimelineVisibility::Internal);

        $customerCodes = collect($customer['timeline'])->pluck('code')->all();
        $this->assertContains('payment_confirmed', $customerCodes);
        $this->assertNotContains('qc_internal', $customerCodes);

        foreach ($customer['timeline'] as $entry) {
            $this->assertSame(TimelineVisibility::Customer->value, $entry['visibility']);
            $meta = $entry['metadata'] ?? [];
            $this->assertNotSame('INTERNAL DAMAGE NOTES', $meta['reason'] ?? null);
        }

        $internalCodes = collect($internal['timeline'])->pluck('code')->all();
        $this->assertContains('qc_internal', $internalCodes);
        $this->assertGreaterThanOrEqual(1, count($customer['timeline']));
        $this->assertTrue(count($internal['timeline']) >= count($customer['timeline']));
    }

    public function test_notification_deduplication_by_idempotency_key(): void
    {
        $user = User::factory()->create();
        $platform = app(NotificationPlatform::class);

        $first = $platform->notifyCustomer(
            NotificationEventType::PaymentConfirmed,
            $user,
            ['order_number' => 'X-1', 'message' => 'Paid'],
            idempotencyKey: 'pay:order-1',
            correlationKey: 'pay:order-1',
        );
        $second = $platform->notifyCustomer(
            NotificationEventType::PaymentConfirmed,
            $user,
            ['order_number' => 'X-1', 'message' => 'Paid again'],
            idempotencyKey: 'pay:order-1',
            correlationKey: 'pay:order-1',
        );

        $this->assertSame(1, Notification::query()
            ->where('correlation_key', 'pay:order-1')
            ->count());
        $this->assertTrue($first->first()->is($second->first()));
    }

    public function test_notification_preference_disables_channel(): void
    {
        $user = User::factory()->create();
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'channel' => NotificationChannel::InApp->value,
            'notification_type' => NotificationEventType::TrackingUpdated->value,
            'is_enabled' => false,
        ]);

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::TrackingUpdated,
            $user,
            ['message' => 'update'],
            idempotencyKey: 'track-pref-1',
        );

        $this->assertCount(0, $created);
    }

    public function test_projection_rebuild_does_not_send_notifications(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'previous_status' => null,
            'new_status' => OrderStatus::Paid->value,
            'source' => 'payment',
            'idempotency_key' => 'paid:'.$order->id,
        ]);

        $before = Notification::query()->count();
        $rows = app(TrackingEngine::class)->rebuildOrderProjection($order->fresh());
        $after = Notification::query()->count();

        $this->assertNotEmpty($rows);
        $this->assertSame($before, $after);
        $this->assertSame(
            count($rows),
            OrderTrackingEvent::query()->where('order_id', $order->id)->count(),
        );

        app(TrackingEngine::class)->rebuildOrderProjection($order->fresh());
        $this->assertSame(
            count($rows),
            OrderTrackingEvent::query()->where('order_id', $order->id)->count(),
        );
    }

    public function test_tracking_event_idempotency_and_customer_api_compatibility(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
        $shipment = Shipment::factory()->create([
            'order_id' => $order->id,
        ]);

        $admin = Admin::factory()->superAdmin()->create();
        $engine = app(TrackingEngine::class);

        $a = $engine->recordEvent($shipment, [
            'event_type' => TrackingEventType::Booked->value,
            'idempotency_key' => 'ship-book:'.$shipment->id,
        ], $admin);
        $b = $engine->recordEvent($shipment, [
            'event_type' => TrackingEventType::Booked->value,
            'idempotency_key' => 'ship-book:'.$shipment->id,
        ], $admin);
        $this->assertTrue($a->is($b));

        Sanctum::actingAs($user);
        $this->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.source', 'shipment_tracking_events')
            ->assertJsonStructure([
                'data' => [
                    'order_number',
                    'current_status',
                    'timeline',
                    'unified_timeline',
                    'shipment_summary',
                ],
            ]);
    }

    public function test_admin_internal_timeline_endpoint(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'previous_status' => null,
            'new_status' => OrderStatus::Paid->value,
            'source' => 'payment',
            'idempotency_key' => 'admin-tl:'.$order->id,
        ]);

        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/admin/orders/{$order->id}/timeline?visibility=internal")
            ->assertOk()
            ->assertJsonPath('data.visibility', 'internal');

        $this->postJson("/api/v1/admin/orders/{$order->id}/timeline/rebuild")
            ->assertOk()
            ->assertJsonPath('data.notifications_unchanged', true);
    }

    public function test_duplicate_delivered_collapsed_in_customer_timeline(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
        ]);

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'previous_status' => OrderStatus::Shipped->value,
            'new_status' => OrderStatus::Delivered->value,
            'source' => 'fulfillment',
            'idempotency_key' => 'delivered-life:'.$order->id,
        ]);

        $shipment = Shipment::factory()->create(['order_id' => $order->id]);
        app(TrackingEngine::class)->recordEvent($shipment, [
            'event_type' => TrackingEventType::Delivered->value,
            'idempotency_key' => 'delivered-track:'.$shipment->id,
        ], Admin::factory()->superAdmin()->create());

        $timeline = app(TrackingEngine::class)->composeOrderTimeline($order->fresh(), TimelineVisibility::Customer);
        $delivered = collect($timeline['timeline'])->where('code', 'delivered');
        $this->assertCount(1, $delivered);
    }
}
