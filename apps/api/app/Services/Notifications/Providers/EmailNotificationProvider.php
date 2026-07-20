<?php

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Services\Notifications\Contracts\NotificationChannelInterface;

/**
 * Email channel — architecture supports SMTP / Mailgun / SendGrid / SES.
 * Credentials not configured in this phase.
 */
class EmailNotificationProvider implements NotificationChannelInterface
{
    public function channel(): string
    {
        return NotificationChannel::Email->value;
    }

    public function providerKey(): string
    {
        return (string) config('notifications.email.driver', 'smtp');
    }

    public function isConfigured(): bool
    {
        return (bool) config('notifications.email.configured', false);
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
