<?php

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationChannel;
use App\Enums\PushTokenProvider;
use App\Models\DevicePushToken;
use App\Models\Notification;
use App\Services\Devices\ResolveActiveExpoPushTokens;
use App\Services\Notifications\Contracts\NotificationChannelInterface;
use App\Services\Notifications\Expo\ExpoPushClient;
use App\Services\Notifications\Expo\ExpoPushMessageBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Push channel — Expo Push Service delivery (Wave 6B).
 * Fail-closed when not configured. Does not poll receipts (Wave 6F).
 */
class PushNotificationProvider implements NotificationChannelInterface
{
    public function __construct(
        private readonly ResolveActiveExpoPushTokens $resolveTokens,
        private readonly ExpoPushMessageBuilder $messages,
        private readonly ExpoPushClient $client,
    ) {}

    public function channel(): string
    {
        return NotificationChannel::Push->value;
    }

    public function providerKey(): string
    {
        return (string) config('notifications.push.driver', PushTokenProvider::Expo->value);
    }

    public function isConfigured(): bool
    {
        if (! (bool) config('notifications.push.configured', false)) {
            return false;
        }

        return $this->providerKey() === PushTokenProvider::Expo->value;
    }

    /**
     * @return array{success: bool, provider_message_id: string|null, error: string|null}
     */
    public function send(Notification $notification): array
    {
        try {
            if (! $this->isConfigured()) {
                return $this->failure('Not Configured');
            }

            if ($this->providerKey() !== PushTokenProvider::Expo->value) {
                return $this->failure('Unsupported push driver');
            }

            $customerId = $notification->customer_id ?? $notification->user_id;
            if (! filled($customerId)) {
                return $this->failure('Customer missing');
            }

            $devices = $this->resolveTokens->forUserId((string) $customerId)
                ->filter(fn (DevicePushToken $device): bool => $this->messages->looksLikeExpoToken(
                    trim((string) $device->push_token),
                ))
                ->values();

            if ($devices->isEmpty()) {
                // Nothing to deliver — not a transport failure.
                return [
                    'success' => true,
                    'provider_message_id' => 'expo:no_devices',
                    'error' => null,
                ];
            }

            $messages = $this->messages->buildMany($notification, $devices);
            if ($messages === [] || count($messages) !== $devices->count()) {
                return $this->failure('No valid Expo push tokens');
            }

            $result = $this->client->sendMessages($messages, $devices->all());

            return [
                'success' => $result['success'],
                'provider_message_id' => $result['provider_message_id'],
                'error' => $result['error'],
            ];
        } catch (Throwable $e) {
            Log::warning('notification.push.send_failed', [
                'notification_id' => $notification->id,
                'error' => Str::limit($e->getMessage(), 200, '…'),
            ]);

            return $this->failure(Str::limit($e->getMessage(), 480, '…'));
        }
    }

    /**
     * @return array{success: bool, provider_message_id: string|null, error: string|null}
     */
    private function failure(string $error): array
    {
        return [
            'success' => false,
            'provider_message_id' => null,
            'error' => $this->sanitize($error),
        ];
    }

    private function sanitize(string $message): string
    {
        $token = trim((string) config('notifications.push.expo.access_token', ''));
        if ($token !== '') {
            $message = str_replace($token, '[redacted]', $message);
        }

        return Str::limit($message, 480, '…');
    }
}
