<?php

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Services\Notifications\Contracts\NotificationChannelInterface;

/**
 * SMS channel — architecture supports Twilio / Africa's Talking / Beem / Local Gateway.
 */
class SMSNotificationProvider implements NotificationChannelInterface
{
    public function channel(): string
    {
        return NotificationChannel::Sms->value;
    }

    public function providerKey(): string
    {
        return (string) config('notifications.sms.driver', 'twilio');
    }

    public function isConfigured(): bool
    {
        return (bool) config('notifications.sms.configured', false);
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
