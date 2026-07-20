<?php

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Services\Notifications\Contracts\NotificationChannelInterface;

/**
 * Only provider that performs real delivery in this phase.
 */
class InAppNotificationProvider implements NotificationChannelInterface
{
    public function channel(): string
    {
        return NotificationChannel::InApp->value;
    }

    public function providerKey(): string
    {
        return 'in_app';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(Notification $notification): array
    {
        // Persistence is the delivery medium for in-app.
        return [
            'success' => true,
            'provider_message_id' => 'in_app:'.$notification->id,
            'error' => null,
        ];
    }
}
