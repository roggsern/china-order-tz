<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Customer inbox helpers (read / unread).
 * Creation MUST go through NotificationPlatform.
 */
class NotificationService
{
    public function __construct(
        private readonly NotificationPlatform $platform,
    ) {}

    /**
     * @deprecated Use NotificationPlatform::notifyCustomer
     */
    public function create(
        User $user,
        NotificationType $type,
        string $title,
        string $message,
        array $data = [],
    ): Notification {
        $eventType = NotificationEventType::tryFrom($type->value)
            ?? NotificationEventType::TrackingUpdated;

        $key = $data['idempotency_key'] ?? null;

        return $this->platform->notifyCustomer(
            $eventType,
            $user,
            array_merge($data, ['message' => $message]),
            channels: [NotificationChannel::InApp],
            title: $title,
            idempotencyKey: is_string($key) ? $key : null,
        )->firstOrFail();
    }

    public function paginateForUser(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return Notification::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('customer_id', $user->id);
            })
            ->where('channel', NotificationChannel::InApp->value)
            ->latest()
            ->paginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return Notification::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('customer_id', $user->id);
            })
            ->where('channel', NotificationChannel::InApp->value)
            ->whereNull('read_at')
            ->where('status', '!=', \App\Enums\NotificationDeliveryStatus::Read->value)
            ->count();
    }

    public function markAsRead(Notification $notification, User $user): Notification
    {
        if (! $this->belongsToUser($notification, $user)) {
            abort(404);
        }

        $notification->markAsRead();

        return $notification->fresh() ?? $notification;
    }

    public function markAllAsRead(User $user): int
    {
        $query = Notification::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('customer_id', $user->id);
            })
            ->where('channel', NotificationChannel::InApp->value)
            ->where(function ($q) {
                $q->whereNull('read_at')
                    ->orWhere('status', '!=', \App\Enums\NotificationDeliveryStatus::Read->value);
            });

        $count = $query->count();

        $query->update([
            'read_at' => now(),
            'status' => \App\Enums\NotificationDeliveryStatus::Read->value,
        ]);

        return $count;
    }

    private function belongsToUser(Notification $notification, User $user): bool
    {
        return $notification->user_id === $user->id
            || $notification->customer_id === $user->id;
    }
}
