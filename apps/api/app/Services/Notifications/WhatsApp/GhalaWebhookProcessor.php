<?php

namespace App\Services\Notifications\WhatsApp;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies Ghala event-subscription payloads. Does not mutate order/payment state.
 *
 * Ghala delivers webhooks at-least-once and unordered. Status for the same
 * message is sequenced by the payload's own provider timestamp, not callback
 * arrival order and not a sent < delivered < read assumption alone.
 *
 * Reprocessing the same logical status (cache/replay-marker loss, or a new
 * X-Ghala-Delivery for the same status) is idempotent: metadata fields are
 * overwritten in place, never appended, and older timestamps cannot regress
 * a newer accepted provider state. Duplicate processing therefore cannot
 * corrupt WhatsApp notification metadata or business order/payment status.
 */
final class GhalaWebhookProcessor
{
    public const EVENT_MESSAGE_STATUS = 'message.status';

    public const EVENT_MESSAGE_RECEIVED = 'message.received';

    public const META_STATUS = 'whatsapp_status';

    public const META_STATUS_AT = 'whatsapp_status_at';

    public const META_STATUS_EVENT_TS = 'whatsapp_status_event_ts';

    /** @var list<string> */
    private const STATUSES = ['sent', 'delivered', 'read', 'failed'];

    public function process(string $event, array $payload): void
    {
        if ($event === self::EVENT_MESSAGE_RECEIVED) {
            Log::info('notification.whatsapp.webhook.message_received_ignored', [
                'reason' => 'inbound_not_handled_in_wave_1',
            ]);

            return;
        }

        if ($event !== self::EVENT_MESSAGE_STATUS) {
            Log::info('notification.whatsapp.webhook.event_ignored', [
                'event' => $event,
            ]);

            return;
        }

        $status = $this->stringValue($payload['status'] ?? data_get($payload, 'message.status'));
        $providerId = $this->stringValue($payload['id'] ?? data_get($payload, 'message.id'));
        $waMessageId = $this->stringValue($payload['wa_message_id'] ?? data_get($payload, 'message.wa_message_id'));
        $occurredAt = $this->extractOccurredAt($payload);
        $incomingTs = $this->parseProviderTimestamp($occurredAt);
        $error = $this->stringValue($payload['error'] ?? $payload['message'] ?? data_get($payload, 'error.message'));

        if ($status === null || ! in_array($status, self::STATUSES, true)) {
            Log::warning('notification.whatsapp.webhook.unknown_status', [
                'status' => $status,
            ]);

            return;
        }

        $notification = $this->findNotification($providerId, $waMessageId);
        if ($notification === null) {
            Log::info('notification.whatsapp.webhook.notification_unmatched', [
                'provider_id_present' => $providerId !== null,
                'wa_message_id_present' => $waMessageId !== null,
            ]);

            return;
        }

        $data = is_array($notification->data) ? $notification->data : [];
        $current = $this->stringValue($data[self::META_STATUS] ?? null);
        $acceptedTs = $this->acceptedProviderTimestamp($data);

        if (! $this->shouldApply($current, $acceptedTs, $status, $incomingTs)) {
            return;
        }

        $data[self::META_STATUS] = $status;
        if ($occurredAt !== null) {
            $data[self::META_STATUS_AT] = $occurredAt;
        }
        if ($incomingTs !== null) {
            $data[self::META_STATUS_EVENT_TS] = $incomingTs;
        }
        if ($waMessageId !== null) {
            $data['whatsapp_wa_message_id'] = $waMessageId;
        }
        if ($providerId !== null) {
            $data['whatsapp_provider_id'] = $providerId;
        }
        if ($status === 'failed') {
            if ($error !== null) {
                $data['whatsapp_error'] = $error;
            }
        } else {
            unset($data['whatsapp_error']);
        }

        $updates = ['data' => $data];
        if ($status === 'failed' && $notification->status === NotificationDeliveryStatus::Sent) {
            $updates['error_message'] = $error ?? 'WhatsApp delivery failed';
        }

        $notification->forceFill($updates)->save();
    }

    private function findNotification(?string $providerId, ?string $waMessageId): ?Notification
    {
        $query = Notification::query()->where('channel', NotificationChannel::WhatsApp->value);

        if ($providerId !== null) {
            $match = (clone $query)
                ->where(function ($inner) use ($providerId): void {
                    $inner->where('provider_message_id', $providerId)
                        ->orWhere('data->whatsapp_provider_id', $providerId);
                })
                ->first();
            if ($match !== null) {
                return $match;
            }
        }

        if ($waMessageId !== null) {
            return $query
                ->where(function ($inner) use ($waMessageId): void {
                    $inner->where('provider_message_id', $waMessageId)
                        ->orWhere('data->whatsapp_wa_message_id', $waMessageId);
                })
                ->first();
        }

        return null;
    }

    /**
     * Newer provider timestamps win. Older events never overwrite newer state.
     * Equal timestamps use arrival-independent status precedence:
     * read > delivered > failed > sent. Same status at the same timestamp is a no-op apply.
     * Untimestamped events never regress a timestamped accepted state.
     */
    private function shouldApply(?string $current, ?int $acceptedTs, string $next, ?int $incomingTs): bool
    {
        if ($incomingTs !== null && $acceptedTs !== null) {
            if ($incomingTs < $acceptedTs) {
                return false;
            }

            if ($incomingTs > $acceptedTs) {
                return true;
            }

            return $this->precedence($next) >= $this->precedence($current ?? '');
        }

        if ($incomingTs === null && $acceptedTs !== null) {
            return $current === $next;
        }

        if ($incomingTs !== null && $acceptedTs === null) {
            return true;
        }

        if ($current === null || $current === $next) {
            return true;
        }

        return $this->precedence($next) >= $this->precedence($current);
    }

    private function precedence(string $status): int
    {
        return match ($status) {
            'sent' => 1,
            'failed' => 2,
            'delivered' => 3,
            'read' => 4,
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function acceptedProviderTimestamp(array $data): ?int
    {
        $stored = $data[self::META_STATUS_EVENT_TS] ?? null;
        if (is_int($stored) || (is_string($stored) && is_numeric($stored))) {
            return (int) $stored;
        }

        return $this->parseProviderTimestamp($data[self::META_STATUS_AT] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractOccurredAt(array $payload): ?string
    {
        foreach ([
            $payload['created_at'] ?? null,
            data_get($payload, 'message.created_at'),
            $payload['timestamp'] ?? null,
            data_get($payload, 'message.timestamp'),
            $payload['status_at'] ?? null,
        ] as $candidate) {
            $value = $this->stringValue($candidate) ?? (is_int($candidate) ? (string) $candidate : null);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function parseProviderTimestamp(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || (is_string($value) && ctype_digit(ltrim((string) $value, '-')))) {
            $n = (int) $value;
            if ($n <= 0) {
                return null;
            }

            return $n < 1_000_000_000_000 ? $n * 1000 : $n;
        }

        if (! is_scalar($value)) {
            return null;
        }

        try {
            $parsed = Carbon::parse(trim((string) $value));
        } catch (Throwable) {
            return null;
        }

        $ms = (int) round($parsed->getPreciseTimestamp(3));

        return $ms > 0 ? $ms : null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value) || $value === '') {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }
}
