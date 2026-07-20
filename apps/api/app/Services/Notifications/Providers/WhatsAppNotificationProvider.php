<?php

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Services\Notifications\Contracts\NotificationChannelInterface;

/**
 * WhatsApp channel — architecture supports Meta / 360Dialog / Twilio / UltraMsg / GreenAPI.
 * Credentials not configured in this phase.
 */
class WhatsAppNotificationProvider implements NotificationChannelInterface
{
    public function channel(): string
    {
        return NotificationChannel::WhatsApp->value;
    }

    public function providerKey(): string
    {
        return (string) config('notifications.whatsapp.driver', 'meta_cloud');
    }

    public function isConfigured(): bool
    {
        return (bool) config('notifications.whatsapp.configured', false);
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
