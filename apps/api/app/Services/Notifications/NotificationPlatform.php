<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\DTOs\NotificationEvent;
use Illuminate\Support\Collection;

/**
 * Authoritative notification facade.
 * Business modules publish events here — never call channel providers directly.
 * Deduplication via idempotency_key. Preferences enforced in dispatcher.
 */
class NotificationPlatform
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * @return Collection<int, Notification>
     */
    public function publish(NotificationEvent $event): Collection
    {
        return $this->dispatcher->dispatch($event);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<NotificationChannel>|null  $channels
     * @return Collection<int, Notification>
     */
    public function notifyCustomer(
        NotificationEventType $type,
        User|string $customer,
        array $data = [],
        ?array $channels = null,
        ?string $title = null,
        ?string $idempotencyKey = null,
        ?string $correlationKey = null,
    ): Collection {
        $customerId = $customer instanceof User ? $customer->id : $customer;

        return $this->publish(new NotificationEvent(
            type: $type,
            data: $data,
            customerId: $customerId,
            channels: $channels,
            title: $title,
            idempotencyKey: $idempotencyKey ?? (isset($data['idempotency_key']) ? (string) $data['idempotency_key'] : null),
            correlationKey: $correlationKey ?? (isset($data['correlation_key']) ? (string) $data['correlation_key'] : null),
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<NotificationChannel>|null  $channels
     * @return Collection<int, Notification>
     */
    public function notifyAdmin(
        NotificationEventType $type,
        Admin|string $admin,
        array $data = [],
        ?array $channels = null,
        ?string $title = null,
        ?string $idempotencyKey = null,
        ?string $correlationKey = null,
    ): Collection {
        $adminId = $admin instanceof Admin ? $admin->id : $admin;

        return $this->publish(new NotificationEvent(
            type: $type,
            data: $data,
            adminId: $adminId,
            channels: $channels,
            title: $title,
            idempotencyKey: $idempotencyKey ?? (isset($data['idempotency_key']) ? (string) $data['idempotency_key'] : null),
            correlationKey: $correlationKey ?? (isset($data['correlation_key']) ? (string) $data['correlation_key'] : null),
        ));
    }
}
