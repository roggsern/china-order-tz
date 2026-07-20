<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Events\Audit\NotificationSent;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Services\Notifications\DTOs\NotificationEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates template resolution, rendering, provider delivery, and logging.
 * Enforces idempotency and channel preferences.
 */
class NotificationDispatcher
{
    public function __construct(
        private readonly NotificationTemplateEngine $templates,
        private readonly NotificationRenderer $renderer,
        private readonly ChannelProviderRegistry $providers,
    ) {}

    /**
     * @return Collection<int, Notification>
     */
    public function dispatch(NotificationEvent $event): Collection
    {
        if ($event->idempotencyKey !== null) {
            $existing = Notification::query()
                ->where('correlation_key', $event->idempotencyKey)
                ->get();
            if ($existing->isNotEmpty()) {
                return $existing;
            }
        }

        $channels = $this->resolveChannels($event);
        $created = collect();

        foreach ($channels as $channel) {
            if (! $this->channelAllowed($event, $channel)) {
                continue;
            }
            $created->push($this->dispatchToChannel($event, $channel));
        }

        return $created;
    }

    public function dispatchToChannel(NotificationEvent $event, NotificationChannel $channel): Notification
    {
        if ($event->idempotencyKey !== null) {
            $channelKey = $event->idempotencyKey.':'.$channel->value;
            $existing = Notification::query()->where('idempotency_key', $channelKey)->first();
            if ($existing !== null) {
                return $existing;
            }
        } else {
            $channelKey = null;
        }

        $template = $this->templates->resolveForEvent($event->type, $channel);
        $variables = $this->normalizeVariables($event->data);

        $title = $event->title;
        $message = '';
        $templateKey = null;

        if ($template !== null) {
            $templateKey = $template->key;
            $rendered = $this->templates->preview($template, $variables, $this->renderer);
            $title = $title ?? $rendered['title'];
            $message = $rendered['body'];
        } else {
            $title = $title ?? $event->type->label();
            $message = (string) ($variables['message'] ?? $event->type->label());
        }

        $provider = $this->providers->resolve($channel);

        $notification = Notification::query()->create([
            'user_id' => $event->customerId,
            'customer_id' => $event->customerId,
            'admin_id' => $event->adminId,
            'type' => $event->type->value,
            'event_type' => $event->type->value,
            'template_key' => $templateKey,
            'title' => $title,
            'message' => $message,
            'channel' => $channel->value,
            'status' => NotificationDeliveryStatus::Processing->value,
            'provider' => $provider->providerKey(),
            'data' => $event->data,
            'idempotency_key' => $channelKey ?? $event->idempotencyKey,
            'correlation_key' => $event->correlationKey ?? $event->idempotencyKey,
            'retry_count' => 0,
        ]);

        try {
            $result = $provider->send($notification);

            if ($result['success']) {
                $notification->update([
                    'status' => NotificationDeliveryStatus::Sent->value,
                    'provider_message_id' => $result['provider_message_id'],
                    'sent_at' => now(),
                    'error_message' => null,
                ]);
            } else {
                $notification->update([
                    'status' => NotificationDeliveryStatus::Failed->value,
                    'error_message' => $result['error'] ?? 'Delivery failed',
                    'provider_message_id' => $result['provider_message_id'],
                    'retry_count' => (int) $notification->retry_count + 1,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('notification.delivery_failed', [
                'notification_id' => $notification->id,
                'channel' => $channel->value,
                'error' => $e->getMessage(),
                'correlation_key' => $event->correlationKey,
            ]);

            $notification->update([
                'status' => NotificationDeliveryStatus::Failed->value,
                'error_message' => $e->getMessage(),
                'retry_count' => (int) $notification->retry_count + 1,
            ]);
        }

        $fresh = $notification->fresh() ?? $notification;
        event(NotificationSent::fromNotification($fresh));

        return $fresh;
    }

    /**
     * @return list<NotificationChannel>
     */
    private function resolveChannels(NotificationEvent $event): array
    {
        if ($event->channels !== null && $event->channels !== []) {
            return $event->channels;
        }

        $configured = config('notifications.event_channels.'.$event->type->value, ['in_app']);

        return collect($configured)
            ->map(fn (string $value) => NotificationChannel::tryFrom($value))
            ->filter()
            ->values()
            ->all();
    }

    private function channelAllowed(NotificationEvent $event, NotificationChannel $channel): bool
    {
        if ($event->customerId === null) {
            return true;
        }

        $pref = NotificationPreference::query()
            ->where('user_id', $event->customerId)
            ->where('channel', $channel->value)
            ->where('notification_type', $event->type->value)
            ->first();

        if ($pref === null) {
            return true;
        }

        return (bool) $pref->is_enabled;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeVariables(array $data): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $flat[(string) $key] = $value;
            }
        }

        return $flat;
    }
}
