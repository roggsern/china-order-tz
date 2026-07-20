<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;

/**
 * Legacy entry point — routes through NotificationPlatform (sole delivery authority).
 */
class CreateNotificationAction
{
    public function __construct(
        private readonly NotificationPlatform $platform,
    ) {}

    public function handle(
        User $user,
        NotificationType $type,
        string $title,
        string $message,
        array $data = [],
    ): Notification {
        $eventType = NotificationEventType::tryFrom($type->value)
            ?? NotificationEventType::TrackingUpdated;

        $orderId = $data['order_id'] ?? null;
        $newStatus = $data['new_status'] ?? null;
        $idempotency = isset($orderId, $newStatus)
            ? 'legacy-shipment-status:'.$orderId.':'.$newStatus
            : ($data['idempotency_key'] ?? null);

        $created = $this->platform->notifyCustomer(
            $eventType,
            $user,
            array_merge($data, ['message' => $message]),
            channels: [NotificationChannel::InApp],
            title: $title,
            idempotencyKey: is_string($idempotency) ? $idempotency : null,
            correlationKey: is_string($idempotency) ? $idempotency : null,
        );

        return $created->first() ?? Notification::query()
            ->where('customer_id', $user->id)
            ->latest()
            ->firstOrFail();
    }
}
