<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GhalaWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_ghala_webhook_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['notifications.whatsapp.webhook_secret' => self::SECRET]);
        Cache::flush();
    }

    public function test_valid_status_updates_whatsapp_metadata_without_changing_order_status(): void
    {
        $notification = $this->whatsappNotification('01GHALA1', 'wamid.ONE');

        $this->postGhala(
            ['id' => '01GHALA1', 'status' => 'delivered', 'wa_message_id' => 'wamid.ONE'],
            'message.status',
            'del-1',
        )->assertOk();

        $fresh = $notification->fresh();
        $this->assertSame('delivered', $fresh->data['whatsapp_status'] ?? null);
        $this->assertSame(NotificationDeliveryStatus::Sent, $fresh->status);
        $this->assertDatabaseMissing('orders', ['id' => $notification->data['order_id'] ?? 'missing']);
    }

    public function test_untimestamped_status_uses_arrival_independent_precedence(): void
    {
        $notification = $this->whatsappNotification('01GHALA2', 'wamid.TWO');

        $this->postGhala(['id' => '01GHALA2', 'status' => 'sent'], 'message.status', 'd-sent')->assertOk();
        $this->assertSame('sent', $notification->fresh()->data['whatsapp_status'] ?? null);

        $this->postGhala(['id' => '01GHALA2', 'status' => 'read'], 'message.status', 'd-read')->assertOk();
        $this->assertSame('read', $notification->fresh()->data['whatsapp_status'] ?? null);
        $this->assertSame(NotificationDeliveryStatus::Sent, $notification->fresh()->status);

        $this->postGhala(['id' => '01GHALA2', 'status' => 'delivered'], 'message.status', 'd-late-delivered')->assertOk();
        $this->assertSame('read', $notification->fresh()->data['whatsapp_status'] ?? null);

        $this->postGhala(['id' => '01GHALA2', 'status' => 'failed', 'error' => 'undeliverable'], 'message.status', 'd-failed')->assertOk();
        $this->assertSame('read', $notification->fresh()->data['whatsapp_status'] ?? null);
    }

    public function test_older_sent_does_not_overwrite_newer_delivered(): void
    {
        $notification = $this->whatsappNotification('01TS1', 'wamid.TS1');

        $this->postGhala($this->statusPayload('01TS1', 'delivered', '2026-08-30T12:00:05Z'), 'message.status', 'ts-delivered')->assertOk();
        $this->postGhala($this->statusPayload('01TS1', 'sent', '2026-08-30T12:00:01Z'), 'message.status', 'ts-older-sent')->assertOk();

        $fresh = $notification->fresh();
        $this->assertSame('delivered', $fresh->data['whatsapp_status'] ?? null);
        $this->assertSame('2026-08-30T12:00:05Z', $fresh->data['whatsapp_status_at'] ?? null);
        $this->assertSame(NotificationDeliveryStatus::Sent, $fresh->status);
    }

    public function test_older_delivered_does_not_overwrite_newer_read(): void
    {
        $notification = $this->whatsappNotification('01TS2', 'wamid.TS2');

        $this->postGhala($this->statusPayload('01TS2', 'read', '2026-08-30T12:00:09Z'), 'message.status', 'ts-read')->assertOk();
        $this->postGhala($this->statusPayload('01TS2', 'delivered', '2026-08-30T12:00:04Z'), 'message.status', 'ts-older-delivered')->assertOk();

        $this->assertSame('read', $notification->fresh()->data['whatsapp_status'] ?? null);
        $this->assertSame('2026-08-30T12:00:09Z', $notification->fresh()->data['whatsapp_status_at'] ?? null);
    }

    public function test_newer_failed_overwrites_older_delivered(): void
    {
        $notification = $this->whatsappNotification('01TS3', 'wamid.TS3');

        $this->postGhala($this->statusPayload('01TS3', 'delivered', '2026-08-30T12:00:04Z'), 'message.status', 'ts-del-old')->assertOk();
        $this->postGhala($this->statusPayload('01TS3', 'failed', '2026-08-30T12:00:08Z', 'handset unreachable'), 'message.status', 'ts-fail-new')->assertOk();

        $fresh = $notification->fresh();
        $this->assertSame('failed', $fresh->data['whatsapp_status'] ?? null);
        $this->assertSame('handset unreachable', $fresh->data['whatsapp_error'] ?? null);
        $this->assertSame(NotificationDeliveryStatus::Sent, $fresh->status);
    }

    public function test_newer_delivered_overwrites_older_failed(): void
    {
        $notification = $this->whatsappNotification('01TS4', 'wamid.TS4');

        $this->postGhala($this->statusPayload('01TS4', 'failed', '2026-08-30T12:00:03Z', 'temporary'), 'message.status', 'ts-fail-old')->assertOk();
        $this->postGhala($this->statusPayload('01TS4', 'delivered', '2026-08-30T12:00:07Z'), 'message.status', 'ts-del-new')->assertOk();

        $fresh = $notification->fresh();
        $this->assertSame('delivered', $fresh->data['whatsapp_status'] ?? null);
        $this->assertArrayNotHasKey('whatsapp_error', $fresh->data ?? []);
        $this->assertSame(NotificationDeliveryStatus::Sent, $fresh->status);
    }

    public function test_equal_timestamp_uses_status_precedence_not_arrival_order(): void
    {
        $first = $this->whatsappNotification('01EQ1', 'wamid.EQ1');
        $this->postGhala($this->statusPayload('01EQ1', 'delivered', '2026-08-30T12:00:10Z'), 'message.status', 'eq-del-first')->assertOk();
        $this->postGhala($this->statusPayload('01EQ1', 'sent', '2026-08-30T12:00:10Z'), 'message.status', 'eq-sent-second')->assertOk();
        $this->assertSame('delivered', $first->fresh()->data['whatsapp_status'] ?? null);

        $second = $this->whatsappNotification('01EQ2', 'wamid.EQ2');
        $this->postGhala($this->statusPayload('01EQ2', 'sent', '2026-08-30T12:00:10Z'), 'message.status', 'eq-sent-first')->assertOk();
        $this->postGhala($this->statusPayload('01EQ2', 'delivered', '2026-08-30T12:00:10Z'), 'message.status', 'eq-del-second')->assertOk();
        $this->assertSame('delivered', $second->fresh()->data['whatsapp_status'] ?? null);

        $failedFirst = $this->whatsappNotification('01EQ3', 'wamid.EQ3');
        $this->postGhala($this->statusPayload('01EQ3', 'failed', '2026-08-30T12:00:10Z', 'x'), 'message.status', 'eq-fail-first')->assertOk();
        $this->postGhala($this->statusPayload('01EQ3', 'delivered', '2026-08-30T12:00:10Z'), 'message.status', 'eq-del-after-fail')->assertOk();
        $this->assertSame('delivered', $failedFirst->fresh()->data['whatsapp_status'] ?? null);

        $deliveredFirst = $this->whatsappNotification('01EQ4', 'wamid.EQ4');
        $this->postGhala($this->statusPayload('01EQ4', 'delivered', '2026-08-30T12:00:10Z'), 'message.status', 'eq-del-before-fail')->assertOk();
        $this->postGhala($this->statusPayload('01EQ4', 'failed', '2026-08-30T12:00:10Z', 'x'), 'message.status', 'eq-fail-after-del')->assertOk();
        $this->assertSame('delivered', $deliveredFirst->fresh()->data['whatsapp_status'] ?? null);
    }

    public function test_duplicate_status_with_different_delivery_ids_is_idempotent(): void
    {
        $notification = $this->whatsappNotification('01DUP1', 'wamid.DUP1');
        $payload = $this->statusPayload('01DUP1', 'delivered', '2026-08-30T12:00:12Z');

        $this->postGhala($payload, 'message.status', 'delivery-a')->assertOk();
        $this->postGhala($payload, 'message.status', 'delivery-b')->assertOk();

        $fresh = $notification->fresh();
        $this->assertSame('delivered', $fresh->data['whatsapp_status'] ?? null);
        $this->assertSame('2026-08-30T12:00:12Z', $fresh->data['whatsapp_status_at'] ?? null);
        $this->assertSame(NotificationDeliveryStatus::Sent, $fresh->status);
    }

    public function test_failed_after_sent_is_recorded(): void
    {
        $notification = $this->whatsappNotification('01GHALA3', 'wamid.THREE');
        $this->postGhala(['id' => '01GHALA3', 'status' => 'sent'], 'message.status', 'f-sent')->assertOk();
        $this->postGhala(['id' => '01GHALA3', 'status' => 'failed', 'error' => 'handset off'], 'message.status', 'f-fail')->assertOk();

        $fresh = $notification->fresh();
        $this->assertSame('failed', $fresh->data['whatsapp_status'] ?? null);
        $this->assertSame('handset off', $fresh->data['whatsapp_error'] ?? null);
    }

    public function test_same_x_ghala_delivery_is_processed_once(): void
    {
        $notification = $this->whatsappNotification('01GHALA4', 'wamid.FOUR');

        $this->postGhala(['id' => '01GHALA4', 'status' => 'delivered'], 'message.status', 'same-delivery')->assertOk();
        $this->postGhala(['id' => '01GHALA4', 'status' => 'read'], 'message.status', 'same-delivery')->assertOk();

        $this->assertSame('delivered', $notification->fresh()->data['whatsapp_status'] ?? null);
    }

    public function test_cache_loss_then_same_logical_status_is_idempotent(): void
    {
        $notification = $this->whatsappNotification('01CACHE1', 'wamid.CACHE1');
        $payload = $this->statusPayload('01CACHE1', 'delivered', '2026-08-30T12:00:20Z');

        $this->postGhala($payload, 'message.status', 'cache-del-1')->assertOk();
        $before = $notification->fresh()->data;

        Cache::flush();

        $this->postGhala($payload, 'message.status', 'cache-del-1')->assertOk();

        $after = $notification->fresh()->data;
        $this->assertSame('delivered', $after['whatsapp_status'] ?? null);
        $this->assertSame($before['whatsapp_status'], $after['whatsapp_status']);
        $this->assertSame($before['whatsapp_status_at'], $after['whatsapp_status_at']);
        $this->assertSame($before['whatsapp_status_event_ts'], $after['whatsapp_status_event_ts']);
        $this->assertSame($before['whatsapp_provider_id'], $after['whatsapp_provider_id']);
        $this->assertSame(NotificationDeliveryStatus::Sent, $notification->fresh()->status);
    }

    public function test_cache_loss_does_not_allow_older_event_to_regress_state(): void
    {
        $notification = $this->whatsappNotification('01CACHE2', 'wamid.CACHE2');

        $this->postGhala($this->statusPayload('01CACHE2', 'read', '2026-08-30T12:00:30Z'), 'message.status', 'cache-read')->assertOk();
        Cache::flush();
        $this->postGhala($this->statusPayload('01CACHE2', 'sent', '2026-08-30T12:00:21Z'), 'message.status', 'cache-older-sent')->assertOk();

        $this->assertSame('read', $notification->fresh()->data['whatsapp_status'] ?? null);
        $this->assertSame('2026-08-30T12:00:30Z', $notification->fresh()->data['whatsapp_status_at'] ?? null);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->call('POST', '/api/v1/webhooks/ghala', [], [], [], [
            'HTTP_X_GHALA_TIMESTAMP' => (string) time(),
            'HTTP_X_GHALA_SIGNATURE' => 'sha256='.str_repeat('b', 64),
            'HTTP_X_GHALA_EVENT' => 'message.status',
            'HTTP_X_GHALA_DELIVERY' => 'bad-sig',
            'CONTENT_TYPE' => 'application/json',
        ], '{"id":"x","status":"sent"}')->assertUnauthorized();
    }

    public function test_message_received_is_acknowledged_without_status_change(): void
    {
        $notification = $this->whatsappNotification('01GHALA5', 'wamid.FIVE');

        $this->postGhala(['id' => 'inbound-1', 'text' => 'hi'], 'message.received', 'inbound-del')->assertOk();

        $this->assertArrayNotHasKey('whatsapp_status', $notification->fresh()->data ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(string $id, string $status, string $createdAt, ?string $error = null): array
    {
        $payload = [
            'id' => $id,
            'status' => $status,
            'created_at' => $createdAt,
        ];

        if ($error !== null) {
            $payload['error'] = $error;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postGhala(array $payload, string $event, string $delivery)
    {
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, self::SECRET);

        return $this->call('POST', '/api/v1/webhooks/ghala', [], [], [], [
            'HTTP_X_GHALA_TIMESTAMP' => $timestamp,
            'HTTP_X_GHALA_SIGNATURE' => $signature,
            'HTTP_X_GHALA_EVENT' => $event,
            'HTTP_X_GHALA_DELIVERY' => $delivery,
            'CONTENT_TYPE' => 'application/json',
        ], $raw);
    }

    private function whatsappNotification(string $providerId, string $waId): Notification
    {
        $user = User::factory()->create(['phone' => '+255712345678']);

        return Notification::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $user->id,
            'channel' => NotificationChannel::WhatsApp,
            'event_type' => NotificationEventType::OrderCreated->value,
            'type' => NotificationEventType::OrderCreated->value,
            'status' => NotificationDeliveryStatus::Sent,
            'provider' => 'ghala',
            'provider_message_id' => $providerId,
            'data' => [
                'whatsapp_provider_id' => $providerId,
                'whatsapp_wa_message_id' => $waId,
            ],
        ]);
    }
}
