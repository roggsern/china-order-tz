<?php

namespace App\Services\Notifications\Contracts;

use App\Models\Notification;

/**
 * Replaceable channel provider contract.
 * Business modules never call providers directly.
 */
interface NotificationChannelInterface
{
    public function channel(): string;

    public function providerKey(): string;

    public function isConfigured(): bool;

    /**
     * @return array{success: bool, provider_message_id: string|null, error: string|null}
     */
    public function send(Notification $notification): array;
}
