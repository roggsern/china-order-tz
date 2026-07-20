<?php

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Services\Notifications\Contracts\NotificationChannelInterface;

/**
 * Push channel — architecture supports Firebase / OneSignal / Expo.
 */
class PushNotificationProvider implements NotificationChannelInterface
{
    public function channel(): string
    {
        return NotificationChannel::Push->value;
    }

    public function providerKey(): string
    {
        return (string) config('notifications.push.driver', 'firebase');
    }

    public function isConfigured(): bool
    {
        return (bool) config('notifications.push.configured', false);
    }

    public function send(Notification $notification): array
    {
        return [
            'success' => false,
            'provider_message_id' => null,
            'error' => 'Not Configured',
        ];
    }
}
